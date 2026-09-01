<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Command;

use Doctrine\ORM\EntityManagerInterface;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use Madcoders\SyliusBrandPlugin\Resolver\ProductBrandSynchronizerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * Rebuilds `sylius_product.brand_id` for the whole catalogue.
 *
 * This is the answer to every write path that does not go through the resource layer: a CSV import,
 * a PIM sync, a direct SQL update - and to a change in the brand mapping, which retroactively
 * changes what every product resolves to. It is idempotent, so running it too often costs only
 * time. See docs/adr-log/0004-brand-resolution-from-a-product-attribute.md.
 */
#[AsCommand(
    name: 'madcoders:brand:resync-products',
    description: 'Recompute the brand of every product from its brand attribute.',
)]
final class ResyncProductBrandsCommand extends Command
{
    use LockableTrait;

    private const int DEFAULT_BATCH_SIZE = 200;

    /**
     * The lock factory is injected rather than left to `LockableTrait`, which otherwise builds its
     * own over `SemaphoreStore`/`FlockStore` - both host-local. On a multi-pod deployment that
     * makes the guard below useless: a cron on one node and a deploy hook on another would each
     * take their own lock and walk the catalogue at the same time. Hosts that have configured
     * `framework.lock` with a shared store (Redis, the database) get a real distributed lock;
     * where the service is absent the argument resolves to null and the trait's local fallback
     * still applies.
     *
     * @param class-string $productClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductBrandSynchronizerInterface $synchronizer,
        private readonly BrandSettingsProviderInterface $settings,
        private readonly string $productClass,
        ?LockFactory $lockFactory = null,
    ) {
        parent::__construct();

        $this->lockFactory = $lockFactory;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'How many products to process before flushing.',
                (string) self::DEFAULT_BATCH_SIZE,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report what would change without writing anything.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // A resync is idempotent, so an overlapping run is not dangerous - it is just the whole
        // catalogue walked twice, at the same time, against the same rows. That is exactly what a
        // nightly cron plus a deploy hook will do to each other sooner or later.
        if (!$this->lock()) {
            $io->warning('Another resync is already running - exiting without doing anything.');

            return Command::SUCCESS;
        }

        try {
            return $this->resync($input, $io);
        } finally {
            $this->release();
        }
    }

    private function resync(InputInterface $input, SymfonyStyle $io): int
    {
        $dryRun = true === $input->getOption('dry-run');

        $batchSizeOption = $input->getOption('batch-size');
        $batchSize = is_numeric($batchSizeOption) ? max(1, (int) $batchSizeOption) : self::DEFAULT_BATCH_SIZE;

        $attributeCode = $this->settings->getBrandAttributeCode();

        if (null === $attributeCode) {
            $io->error('No brand attribute is configured. Set one in Admin → Settings → Brands before resyncing.');

            return Command::FAILURE;
        }

        $io->title('Resyncing product brands');
        $io->text(\sprintf('Brand attribute: <info>%s</info>', $attributeCode));
        $io->text(\sprintf('Mapping entries: <info>%d</info>', \count($this->settings->getBrandMapping())));

        if ($dryRun) {
            $io->note('Dry run: no changes will be written.');
        }

        $processed = 0;
        $changed = 0;

        foreach ($this->iterateProducts($batchSize) as $product) {
            ++$processed;

            if ($this->synchronizer->synchronize($product)) {
                ++$changed;
            }

            if (0 === $processed % $batchSize) {
                $this->finishBatch($dryRun);
                $io->text(\sprintf('  … %d products processed', $processed));
            }
        }

        $this->finishBatch($dryRun);

        $io->success(\sprintf(
            '%d product(s) processed, %d brand assignment(s) %s.',
            $processed,
            $changed,
            $dryRun ? 'would change' : 'changed',
        ));

        return Command::SUCCESS;
    }

    /**
     * Streams the catalogue in id-ordered pages instead of loading it in one go: a resync runs
     * against every product a shop has, and hydrating all of them at once is how this command would
     * fall over on the shops that need it most. Paging by a stable ascending id is safe here
     * because the command never adds or removes products.
     *
     * @return iterable<ProductInterface>
     */
    private function iterateProducts(int $batchSize): iterable
    {
        $offset = 0;

        while (true) {
            /** @var array<array-key, ProductInterface> $products */
            $products = $this->entityManager->createQueryBuilder()
                ->select('o')
                ->from($this->productClass, 'o')
                ->addOrderBy('o.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult()
            ;

            if ([] === $products) {
                return;
            }

            yield from $products;

            $offset += $batchSize;
        }
    }

    private function finishBatch(bool $dryRun): void
    {
        if (!$dryRun) {
            $this->entityManager->flush();
        }

        // Clearing either way: on a dry run it is what discards the pending changes, and on a real
        // run it is what keeps the identity map from growing to the size of the catalogue.
        $this->entityManager->clear();

        // Every brand the resolver memoised is now detached, and assigning a detached entity to a
        // managed product is a Doctrine error. The cache has to go with the identity map.
        $this->synchronizer->reset();
    }
}
