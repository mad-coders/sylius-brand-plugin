<?php

declare(strict_types=1);

return [
    // The Settings plugin is a hard dependency: the brand plugin prepends its settings section into
    // it, and reads every piece of its configuration through it.
    MonsieurBiz\SyliusSettingsPlugin\MonsieurBizSyliusSettingsPlugin::class => ['all' => true],
    Madcoders\SyliusBrandPlugin\MadcodersSyliusBrandPlugin::class => ['all' => true],
];
