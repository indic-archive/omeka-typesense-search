# Omeka-s Typesense Module

This module allows you to use Typesense as the search engine for your Omeka-s site. Typesense is an open-source, typo-tolerant search engine that delivers fast and relevant search results.

## Installation

- Setup typesense server

```bash
docker run -p 8108:8108 -v/tmp/data:/data typesense/typesense:0.23.1 --data-dir /data --api-key=Hu52dwsas2AdxdE
```

- Add mock data

```python
import typesense

client = typesense.Client({
  'api_key': 'Hu52dwsas2AdxdE',
  'nodes': [{
    'host': 'localhost',
    'port': '8108',
    'protocol': 'http'
  }],
  'connection_timeout_seconds': 2
})

create_response = client.collections.create({
  "name": "books",
  "fields": [
    {"name": "title", "type": "string" },
    {"name": "topics", "type": "string" },
    {"name": "publisher", "type": "string", "facet": True }
  ],
});

document = {
   "title": "1956 ഒക്ടോബർ- ജ്ഞാനനിക്ഷേപം - പുസ്തകം 59 ലക്കം 10",
   "topics": "Jnananikshepam",
   "publisher": "The Diocesan Publication Department"
}

client.collections['books'].documents.create(document)
```

Or get the production data using sql script located in testdata/pull-items.sql and get a /tmp/data.csv. Create the index using the following script with the data.

```bash
python testdata/ts_helper.py create
```

- Install the dependencies

```bash
composer install
```

Build minified assets folder and watch for changes
```bash
npm run minify
```

- Copy this directory to omeka-s installation directory under `/var/www/html/omeka/modules`.

- Enable the module from the Admin → Modules menu.

- Configure the module by going to the Configure form.

## Configuration

The configuration form for this module allows you to specify the following options:

- Typesense url: The url of your Typesense server.

- API Key: The API key for your Typesense server. This can be found in the Typesense Admin Dashboard.

- Search index: Name of the search index.

- Index properties: From the right side pane, select the properties of `Omeka\Entity\Items` you require to index.

## Usage

Once the module is installed and configured, search on your Omeka-s site will be powered by Typesense.

## Credits

- Carlos Roso - https://github.com/caroso1222/amazon-autocomplete. This repo's autocomplete.js is a modified version of his work.
