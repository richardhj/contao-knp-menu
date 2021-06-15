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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addField('menuAlias', 'nav_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('navigation', 'tl_module')
    ->applyToPalette('customnav', 'tl_module')
;

$GLOBALS['TL_DCA']['tl_module']['fields']['menuAlias'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'w50 clr', 'unique' => true],
    'sql'       => "varchar(255) NOT NULL default ''",
];
