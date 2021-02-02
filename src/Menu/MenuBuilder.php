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

use Contao\CoreBundle\Event\MenuEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\PageModel;
use Contao\StringUtil;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuItem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MenuBuilder
{
    private FactoryInterface         $factory;
    private RequestStack             $requestStack;
    private ContaoFramework          $framework;
    private EventDispatcherInterface $dispatcher;

    public function __construct(
        FactoryInterface $factory,
        RequestStack $requestStack,
        ContaoFramework $framework,
        EventDispatcherInterface $dispatcher
    ) {
        $this->factory      = $factory;
        $this->requestStack = $requestStack;
        $this->framework    = $framework;
        $this->dispatcher   = $dispatcher;
    }

    public function getMenu(ItemInterface $root, int $pid, $level = 1, $host = null, array $options = []): ItemInterface
    {
        $groups      = [];
        $request     = $this->requestStack->getCurrentRequest();
        $requestPage = $request->attributes->get('pageModel');

        /** @var FrontendUser $user */
        $user = $this->framework->createInstance(FrontendUser::class);
        if ($user->id) {
            $groups = $user->groups;
        }

        $pages = ('customnav' === $options['type']) ?
            PageModel::findMultipleByIds(StringUtil::deserialize($options['pages'], true)) :
            PageModel::findPublishedSubpagesWithoutGuestsByPid($pid, $options['showHidden']);

        if (null === $pages) {
            return $root;
        }

        foreach ($pages as $page) {
            $item = new MenuItem($page->title, $this->factory);

            // Override the domain (see #3765)
            if (null !== $host) {
                $page->domain = $host;
            }

            $_groups = StringUtil::deserialize($page->groups, true);

            if (!$options['showProtected'] && ($page->protected && !\count(array_intersect($_groups, $groups)))) {
                continue;
            }

            // Check whether there will be subpages
            if ($page->subpages > 0) {
                $this->getMenu($item, (int) $page->id, $level++, $host);

                $childRecords = Database::getInstance()->getChildRecords($page->id, 'tl_page');
                if (!$options['showLevel']
                    || $options['showLevel'] >= $level
                    || (!$options['hardLimit']
                        && ((null !== $requestPage && $requestPage->id === $page->id) || \in_array($requestPage->id, $childRecords, true)))) {
                    $item->setDisplayChildren(false);
                }
            }

            switch ($page->type) {
                case 'redirect':
                    $href = $page->url;

                    if (0 === strncasecmp($href, 'mailto:', 7)) {
                        $href = StringUtil::encodeEmail($href);
                    }
                    break;

                case 'forward':
                    if ($page->jumpTo) {
                        $jumpTo = PageModel::findPublishedById($page->jumpTo);
                    } else {
                        $jumpTo = PageModel::findFirstPublishedRegularByPid($page->id);
                    }

                    // Hide the link if the target page is invisible
                    if (!$jumpTo instanceof PageModel || (!$jumpTo->loadDetails()->isPublic)) {
                        $item->setDisplay(false);
                    }

                    $href = $jumpTo->getFrontendUrl();
                    break;

                default:
                    $href = $page->getFrontendUrl();
                    break;
            }

            $this->populateMenuItem($item, $requestPage, $page, $href);
            $root->addChild($item);
        }

        $menuEvent = new MenuEvent($this->factory, $root);
        $this->dispatcher->dispatch($menuEvent);

        return $root;
    }

    private function populateMenuItem(MenuItem $item, ?PageModel $requestPage, PageModel $page, $href): MenuItem
    {
        $extra = $page->row();
        $trail = null !== $requestPage ? \in_array($page->id, $requestPage->trail, true) : false;

        $item->setUri($href);

        // Use the path without query string to check for active pages (see #480)
        $path = current(explode('?', Environment::get('request'), 2));

        // Active page
        if ($href === $path
            && ((null !== $requestPage && $requestPage->id === $page->id) || ('forward' === $page->type && $requestPage->id === $page->jumpTo))) {
            $extra['isActive'] = true;
            $extra['isTrail']  = false;

            $item->setCurrent(true);
        } else {
            $extra['isActive'] = false;
            $extra['isTrail']  = $trail;
        }

        $extra['class'] = trim($page->cssClass);

        $arrRel = [];

        if (0 === strncmp($page->robots, 'noindex,nofollow', 16)) {
            $arrRel[] = 'nofollow';
        }

        // Override the link target
        if ('redirect' === $page->type && $page->target) {
            $arrRel[] = 'noreferrer';
            $arrRel[] = 'noopener';

            $item->setLinkAttribute('target', '_blank');
        }

        // Set the rel attribute
        if (!empty($arrRel)) {
            $item->setLinkAttribute('rel', implode(' ', $arrRel));
        }

        foreach ($extra as $k => $v) {
            $item->setExtra($k, $v);
        }

        return $item;
    }
}
