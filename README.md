# Omeka-s Typesense Module

This module allows you to use Typesense as the search engine for your Omeka-s site. Typesense is an open-source, typo-tolerant search engine that delivers fast and relevant search results.

## Installation

* Install the Typesense server. Follow the instructions at https://docs.typesense.org/getting-started/installation/ to install Typesense.

* Install the Typesense PHP client library by running the following command:

```bash
composer require typesense/typesense
```

* Install this module by copying the Typesense directory into the modules directory of your Omeka-s installation.

* Enable the module from the Admin → Modules menu.

* Configure the module by going to the Configure form (located at /admin/module/Typesense). Enter the host and API key for your Typesense server.

## Configuration

The configuration form for this module allows you to specify the following options:

* Typesense Host: The hostname of your Typesense server.

* API Key: The API key for your Typesense server. This can be found in the Typesense Admin Dashboard.

## Usage

Once the module is installed and configured, search on your Omeka-s site will be powered by Typesense.
