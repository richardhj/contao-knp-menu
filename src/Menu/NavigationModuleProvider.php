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

namespace Richardhj\ContaoKnpMenuBundle\Menu;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\PageModel;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\Provider\MenuProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NavigationModuleProvider implements MenuProviderInterface
{
    private FactoryInterface $factory;
    private MenuBuilder      $builder;
    private ContaoFramework  $framework;
    private RequestStack     $requestStack;

    public function __construct(FactoryInterface $factory, MenuBuilder $builder, ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->factory      = $factory;
        $this->builder      = $builder;
        $this->framework    = $framework;
        $this->requestStack = $requestStack;
    }

    public function get($name, array $options = []): ItemInterface
    {
        $request       = $this->requestStack->getCurrentRequest();
        $moduleAdapter = $this->framework->getAdapter(ModuleModel::class);
        $pageAdapter   = $this->framework->getAdapter(PageModel::class);

        /** @var ModuleModel $module */
        if (null === $module = $moduleAdapter->findBy('menuAlias', $name)) {
            throw new \InvalidArgumentException(sprintf('The menu "%s" is not defined.', $name));
        }

        $currentPage = null !== $request ? $request->attributes->get('pageModel') : null;

        if (is_numeric($currentPage)) {
            $currentPage = $pageAdapter->findByPk($currentPage);
        }

        $menu    = $this->factory->createItem('root');
        $options = array_merge($module->row(), $options);

        // Set the trail and level
        if ($options['defineRoot'] && $options['rootPage'] > 0) {
            $trail = [$options['rootPage']];
            $level = 0;
        } elseif (null === $currentPage) {
            throw new \RuntimeException('Current request does not have a page model. Please define the root page in the navigation module.');
        } else {
            $trail = $currentPage->trail;
            $level = ($options['levelOffset'] > 0) ? $options['levelOffset'] : 0;
        }

        // Overwrite the domain and language if the reference page belongs to a different root page (see #3765)
        if ($options['defineRoot']
            && $options['rootPage'] > 0
            && (null !== $rootPage = PageModel::findWithDetails($options['rootPage']))
            && $rootPage->rootId !== $currentPage->rootId
            && $rootPage->domain
            && $rootPage->domain !== $currentPage->domain) {
            $host = $rootPage->domain;
        }

        return $this->builder->getMenu($menu, (int) $trail[$level], 1, $host ?? null, $options);
    }

    public function has($name, array $options = []): bool
    {
        $adapter = $this->framework->getAdapter(ModuleModel::class);
        $module  = $adapter->findBy('menuAlias', $name);

        return null !== $module;
    }
}
