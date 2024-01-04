<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-knp-menu.
 *
 * (c) Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 *
 * @license MIT
 */

namespace Richardhj\ContaoKnpMenuBundle\Menu;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Model\Collection;
use Contao\PageModel;
use Contao\StringUtil;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuItem;
use Richardhj\ContaoKnpMenuBundle\Event\FrontendMenuEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MenuBuilder
{
    private FactoryInterface $factory;
    private RequestStack $requestStack;
    private ContaoFramework $framework;
    private EventDispatcherInterface $dispatcher;
    private PageRegistry $pageRegistry;
    private TokenChecker $tokenChecker;

    public function __construct(FactoryInterface $factory, RequestStack $requestStack, ContaoFramework $framework, EventDispatcherInterface $dispatcher, PageRegistry $pageRegistry, TokenChecker $tokenChecker)
    {
        $this->factory = $factory;
        $this->requestStack = $requestStack;
        $this->framework = $framework;
        $this->dispatcher = $dispatcher;
        $this->pageRegistry = $pageRegistry;
        $this->tokenChecker = $tokenChecker;
    }

    public function getMenu(ItemInterface $root, int $pid, $level = 1, $host = null, array $options = []): ItemInterface
    {
        if (null === ($pages = $this->getPages($pid, $options))) {
            return $root;
        }

        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        $groups = [];
        $request = $this->requestStack->getCurrentRequest();
        $requestPage = $request->attributes->get('pageModel');

        if (is_numeric($requestPage)) {
            $requestPage = $pageAdapter->findByPk($requestPage);
        }

        /** @var FrontendUser $user */
        $user = $this->framework->createInstance(FrontendUser::class);

        if ($user->id) {
            $groups = $user->groups;
        }

        foreach ($pages as $page) {
            $item = new MenuItem($page->title, $this->factory);

            // Override the domain (see #3765)
            if (null !== $host) {
                $page->domain = $host;
            }

            $_groups = StringUtil::deserialize($page->groups, true);

            if (!($options['showProtected'] ?? false) && ($page->protected && !\count(array_intersect($_groups, $groups)))) {
                continue;
            }

            // Check whether there will be subpages
            if ($page->subpages > 0) {
                ++$level;
                $childRecords = Database::getInstance()->getChildRecords($page->id, 'tl_page');

                $item->setDisplayChildren(false);

                if (
                    !($options['showLevel'] ?? 0) || ($options['showLevel'] ?? 0) >= $level ||
                    (
                        !($options['hardLimit'] ?? 0) && ($requestPage->id === $page->id || \in_array($requestPage->id, $childRecords, true))
                    )
                ) {
                    $item->setDisplayChildren(true);
                }

                $this->getMenu($item, (int) $page->id, $level, $host, $options);
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

        $menuEvent = new FrontendMenuEvent($this->factory, $root);
        $this->dispatcher->dispatch($menuEvent);

        return $root;
    }

    private function getPages(int $pid, array $options): ?Collection
    {
        if ('customnav' !== ($options['type'] ?? '')) {
            $time = Date::floorToMinute();
            $beUserLoggedIn = $this->tokenChecker->isPreviewMode();
            $unroutableTypes = $this->pageRegistry->getUnroutableTypes();

            $arrPages = Database::getInstance()->prepare("SELECT p1.*, EXISTS(SELECT * FROM tl_page p2 WHERE p2.pid=p1.id AND p2.type!='root' AND p2.type NOT IN ('".implode("', '", $unroutableTypes)."')".(!($options['showHidden'] ?? false) ? ' AND p2.hide=0' : '').(!$beUserLoggedIn ? " AND p2.published=1 AND (p2.start='' OR p2.start<=$time) AND (p2.stop='' OR p2.stop>$time)" : '').") AS subpages FROM tl_page p1 WHERE p1.pid=? AND p1.type!='root' AND p1.type NOT IN ('".implode("', '", $unroutableTypes)."')".(!($options['showHidden'] ?? false) ? ' AND p1.hide=0' : '').(!$beUserLoggedIn ? " AND p1.published=1 AND (p1.start='' OR p1.start<=$time) AND (p1.stop='' OR p1.stop>$time)" : '').' ORDER BY p1.sorting')
                ->execute($pid)
            ;

            if ($arrPages->numRows < 1) {
                return null;
            }

            return Collection::createFromDbResult($arrPages, 'tl_page');
        }

        $ids = StringUtil::deserialize(($options['pages'] ?? ''), true);

        if (method_exists(PageModel::class, 'findPublishedRegularWithoutGuestsByIds')) {
            return PageModel::findPublishedRegularWithoutGuestsByIds($ids, ['includeRoot' => true]);
        }

        return PageModel::findPublishedRegularByIds($ids, ['includeRoot' => true]);
    }

    private function populateMenuItem(MenuItem $item, ?PageModel $requestPage, PageModel $page, $href): MenuItem
    {
        $extra = $page->row();
        $trail = null !== $requestPage ? \in_array($page->id, $requestPage->trail, true) : false;

        $item->setUri($href);

        // Use the path without query string to check for active pages (see #480)
        $path = current(explode('?', Environment::get('request'), 2));

        // Active page
        if (
            $href === $path
            && ((null !== $requestPage && $requestPage->id === $page->id) || ('forward' === $page->type && $requestPage->id === $page->jumpTo))
        ) {
            $extra['isActive'] = true;
            $extra['isTrail'] = false;

            $item->setCurrent(true);
        } else {
            $extra['isActive'] = false;
            $extra['isTrail'] = $trail;
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

        if ($title = $page->pageTitle ?: $page->title) {
            $item->setLinkAttribute('title', $title);
        }

        if ($page->accesskey) {
            $item->setLinkAttribute('accesskey', $page->accesskey);
        }

        if ($page->tabindex) {
            $item->setLinkAttribute('tabindex', $page->tabindex);
        }

        foreach ($extra as $k => $v) {
            $item->setExtra($k, $v);
        }

        return $item;
    }
}
