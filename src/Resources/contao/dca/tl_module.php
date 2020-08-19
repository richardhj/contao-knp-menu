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

foreach (['navigation', 'customnav'] as $item) {
    $GLOBALS['TL_DCA']['tl_module']['palettes'][$item] =
        str_replace('{nav_legend}', '{nav_legend},menuAlias', $GLOBALS['TL_DCA']['tl_module']['palettes'][$item]);
}

$GLOBALS['TL_DCA']['tl_module']['fields']['menuAlias'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'w50', 'unique' => true],
    'sql'       => "varchar(255) NOT NULL default ''",
];
