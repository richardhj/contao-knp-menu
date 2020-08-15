# contao-knp-menu

[![Latest Version on Packagist][ico-version]][link-packagist]

Provides Contao navigation modules as instances of [KnpMenu](https://github.com/KnpLabs/KnpMenuBundle).


## Install

Via Composer

``` bash
$ composer require richardhj/contao-knp-menu
```

## Usage

### Create a navigation module

Create a navigation module as you normally do.

Define a menu alias, if you want to.

### Retrieve a navigation module

You can retrieve a module via its id or alias.

```twig
{% set menu = knp_menu_get('main_navigation') %}
{% set menu = knp_menu_get('tl_module.3') %}
```


### Render a menu within a twig template

```twig
<div class="flex items-center justify-between py-2 hidden sm:block">
    {% set menu = knp_menu_get('main_navigation') %}
    <nav itemscope itemtype="http://schema.org/SiteNavigationElement">
        <div class="flex">
            {% for item in menu.children %}
                {% if item.current %}
                    <strong class="inline-block font-bold text-blue-800 px-3 py-2 leading-5" aria-current="page">
                        <span>{{ item.label }}</span>
                    </strong>
                {% else %}
                    <a href="{{ item.uri }}"
                       class="inline-block px-3 py-2 font-normal leading-5 text-gray-500 hover:text-gray-700 focus:outline-none focus:text-blue-400 transition duration-150 ease-in-out"
                       itemprop="url">
                        <span itemprop="name">{{ item.label }}</span>
                    </a>
                {% endif %}
            {% endfor %}
        </div>
    </nav>
</div>
```

My tip: works best if you use [m-vo/contao-twig](https://github.com/m-vo/contao-twig/) to replace the html modules with twig templates. You can use a fe_page.html.twig template and extract components, e.g. header and navigation like: 

```twig
{% block header %}
    {% include 'Layout/header.html.twig' %}
{% endblock %}
```

### Modify navigation

Modify an existing navigation:

```php
class MenuListener
{
    public function __invoke(\Contao\CoreBundle\Event\MenuEvent $event): void
    {
        $name = $event->getTree()->getName();

        if ('mainMenu' !== $name) {
            return;
        }

        // Do stuff.
    }
```


[ico-version]: https://img.shields.io/packagist/v/richardhj/contao-knp-menu.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-LGPL-brightgreen.svg?style=flat-square
[ico-dependencies]: https://www.versioneye.com/php/richardhj:contao-knp-menu/badge.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/richardhj/contao-knp-menu
[link-dependencies]: https://www.versioneye.com/php/richardhj:contao-knp-menu
