#!/usr/bin/env python
"""
Usage:
    python ts_helper.py create -> creates a book index
    python ts_helper.py delete -> deletes the above index
    python ts_helper.py search "query"
"""
import os
import csv
import typesense
import argparse

client = typesense.Client(
    {
        "api_key": os.getenv("TYPESENSE_API_KEY", "Hu52dwsas2AdxdE"),
        "nodes": [
            {
                "host": os.getenv("TYPESENSE_HOST", "localhost"),
                "port": os.getenv("TYPESENSE_PORT", "8108"),
                "protocol": os.getenv("TYPESENSE_PROTOCOL", "http"),
            }
        ],
        "connection_timeout_seconds": 300,
    }
)


def create_index():
    client.collections.create(
        {
            "name": "books",
            "fields": [
                {"name": "dcterms_identifier", "type": "string[]"},
                {
                    "name": "dcterms_title",
                    "type": "string[]",
                    "infix": True,
                },
                {
                    "name": "dcterms_alternative",
                    "type": "string[]",
                    "infix": True,
                },
                {"name": "dcterms_abstract", "type": "string[]"},
                {"name": "dcterms_creator", "type": "string[]"},
                {
                    "name": "dcterms_issued",
                    "type": "string[]",
                },  # issue after adding this
                {"name": "dcterms_subject", "type": "string[]"},
                {"name": "dcterms_language", "type": "string[]"},
                {"name": "dcterms_extent", "type": "string[]"},
                {"name": "dcterms_publisher", "type": "string[]"},
                {"name": "bibo_producer", "type": "string[]"},
                {"name": "dcterms_coverage", "type": "string[]"},
            ],
            "token_separators": ["-"],
        }
    )

    documents = []
    with open("/tmp/data.csv", "r") as f:
        csv_reader = csv.reader(
            f,
            quotechar='"',
            delimiter=",",
            quoting=csv.QUOTE_ALL,
            skipinitialspace=True,
        )

        for line in csv_reader:
            # Validations
            if len(line) < 4:
                continue

            keys = line[2].split("|")
            values = line[3].split("|")
            if len(keys) == 0:
                continue
            if len(values) == 0:
                continue

            document = {
                "dcterms_identifier": [],
                "dcterms_title": [],
                "dcterms_alternative": [],
                "dcterms_abstract": [],
                "dcterms_creator": [],
                "dcterms_issued": [],
                "dcterms_subject": [],
                "dcterms_language": [],
                "dcterms_extent": [],
                "dcterms_publisher": [],
                "bibo_producer": [],
                "dcterms_coverage": [],
            }
            for k, v in zip(keys, values):
                # https://github.com/typesense/typesense/issues/855
                # non-string fields have to indexed as string to make it searchable.
                document.setdefault(k, []).append(str(v))
            documents.append(document)

    client.collections["books"].documents.import_(
        documents, params={"action": "create"}, batch_size=100
    )


def delete_index():
    client.collections["books"].delete()


def search_index(query):
    resp = client.collections["books"].documents.search(
        {
            "q": query,
            "query_by": "dcterms_title,dcterms_alternative,dcterms_creator,dcterms_subject,dcterms_abstract,dcterms_publisher",
            "query_by_weights": "5,5,2,1,1,1",
            "per_page": 15,
            "infix": "fallback",
            # "sort_by": "dcterms_issued:desc",
        }
    )
    print(resp)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Index all docs")
    parser.add_argument("action", action="store", type=str, help="Action to perform")
    parser.add_argument(
        "query", action="store", nargs="?", type=str, help="Action to perform"
    )
    args = parser.parse_args()

    if args.action == "create":
        create_index()
    elif args.action == "delete":
        delete_index()
    elif args.action == "search":
        search_index(args.query)
