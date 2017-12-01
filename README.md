# Divante Elasticsearch Bundle for Oro Commerce
Divante Elasticsearch Bundle is a bundle for Oro Commerce which enables using Elasticsearch as search engine. It allows to quickly search through massive volume of data. With Divante Elasticsearch Bundle you can run an ecommerce website with hundreds of thousands products.

**Table of Contents**

- [Divante Elasticsearch Bundle for Oro Commerce](#)
	- [Features](#)
	- [Compatibility](#)
	- [Installing/Getting started](#)
	- [Configuration](#)
	- [Contributing](#)
	- [Screenshots](#)
	- [Licensing](#)
	- [Standards & Code Quality](#)
	- [About Authors](#)

## Features
1. Searching the catalog
1. Browsing the catalog
1. Search autocomplete

## Compatibility
This module is compatible with Oro Commerce 1.3

## Installing/Getting started

1. Install the bundle
    ```
    composer require divante-ltd/orocommerce-elasticsearch-bundle
    ```
1. Add to `app/config/config.yml`

    ```
    oro_website_search:
        engine: 'elasticsearch'

    divante_elasticsearch:
        index: oro
    ```

1. Remove cache
    ```
    rm -rf app/cache/{dev,prod}
    ```
1. Reindex
    ```
    app/console oro:website-search:reindex
    ```

## Configuration

### Index name
You can change it by editing the `app/config/config.yml`
```
divante_elasticsearch:
    index: oro
```

## Screenshots

![Autocomplete](https://raw.githubusercontent.com/DivanteLtd/orocommerce-elasticsearch-bundle/develop/Resources/doc/autocomplete.png)

![Result list](https://raw.githubusercontent.com/DivanteLtd/orocommerce-elasticsearch-bundle/develop/Resources/doc/search.png)

## Contributing

If you'd like to contribute, please fork the repository and use a feature branch. Pull requests are warmly welcome.

## Licensing

The code in this project is licensed under MIT license.

## Standards & Code Quality

This module respects all Oro Commerce code quality rules and our own PHPCS and PHPMD rulesets.

## About Authors


![Divante-logo](http://divante.co/logo-HG.png "Divante")

We are a Software House from Europe, existing from 2008 and employing about 150 people. Our core competencies are built around Magento, Pimcore and bespoke software projects (we love Symfony3, Node.js, Angular, React, Vue.js). We specialize in sophisticated integration projects trying to connect hardcore IT with good product design and UX.

We work for Clients like INTERSPORT, ING, Odlo, Onderdelenwinkel or CDP, the company that produced The Witcher game. We develop two projects: [Open Loyalty](http://www.openloyalty.io/ "Open Loyalty") - loyalty program in open source and [Vue.js Storefront](https://github.com/DivanteLtd/vue-storefront "Vue.js Storefront").

We are part of the OEX Group which is listed on the Warsaw Stock Exchange. Our annual revenue has been growing at a minimum of about 30% year on year.

Visit our website [Divante.co](https://divante.co/ "Divante.co") for more information.
