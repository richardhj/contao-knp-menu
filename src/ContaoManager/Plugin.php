<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-knp-menu.
 *
 * Copyright (c) 2020-2021 Richard Henkenjohann
 *
 * @package   richardhj/contao-knp-menu
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2020-2021 Richard Henkenjohann
 * @license   MIT
 */

namespace Richardhj\ContaoKnpMenuBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Config\ConfigInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Richardhj\ContaoKnpMenuBundle\RichardhjContaoKnpMenuBundle;

class Plugin implements BundlePluginInterface
{
    /**
     * Gets a list of autoload configurations for this bundle.
     *
     * @return ConfigInterface[]
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(RichardhjContaoKnpMenuBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
