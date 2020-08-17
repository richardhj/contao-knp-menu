<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-knp-menu.
 *
 * Copyright (c) 2020-2020 Richard Henkenjohann
 *
 * @package   richardhj/contao-knp-menu
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2020-2020 Richard Henkenjohann
 * @license   MIT
 */

namespace Richardhj\ContaoKnpMenuBundle\Menu;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\PageModel;
use Knp\Menu\FactoryInterface;
use Knp\Menu\Provider\MenuProviderInterface;

class NavigationModuleProvider implements MenuProviderInterface
{
    private FactoryInterface $factory;
    private MenuBuilder      $builder;
    private ContaoFramework $framework;

    public function __construct(FactoryInterface $factory, MenuBuilder $builder, ContaoFramework $framework)
    {
        $this->factory   = $factory;
        $this->builder   = $builder;
        $this->framework = $framework;
    }

    public function get($name, array $options = [])
    {
        $moduleAdapter = $this->framework->getAdapter(ModuleModel::class);

        /** @var ModuleModel $module */
        if (null === $module = $moduleAdapter->findBy('menuAlias', $name)) {
            throw new \InvalidArgumentException(sprintf('The menu "%s" is not defined.', $name));
        }

        $menu = $this->factory->createItem('root');

        /* @var PageModel $objPage */
        global $objPage;

        // Set the trail and level
        if ($module->defineRoot && $module->rootPage > 0) {
            $trail = [$module->rootPage];
            $level = 0;
        } else {
            $trail = $objPage->trail;
            $level = ($module->levelOffset > 0) ? $module->levelOffset : 0;
        }

        // Overwrite the domain and language if the reference page belongs to a different root page (see #3765)
        if ($module->defineRoot
            && $module->rootPage > 0
            && (null !== $rootPage = PageModel::findWithDetails($module->rootPage))
            && $rootPage->rootId !== $objPage->rootId
            && $rootPage->domain
            && $rootPage->domain !== $objPage->domain) {
            $host = $rootPage->domain;
        }

        return $this->builder->getMenu($menu, (int) $trail[$level], 1, $host ?? null, $module->row());
    }

    public function has($name, array $options = [])
    {
        $moduleAdapter = $this->framework->getAdapter(ModuleModel::class);
        $module        = $moduleAdapter->findBy('menuAlias', $name);

        return null !== $module;
    }
}
