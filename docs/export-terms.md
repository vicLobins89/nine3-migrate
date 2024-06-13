# Export Terms

This page allows the export of taxonomy terms along with any attached meta. Currently only the JSON format is supported.

URL: `[SITE URL]/wp-admin/admin.php?page=nine3-migrate-page&tab=export-terms`

## Settings

Just choose the taxonomy you want to export, save and click the Download JSON button when it appears.

## Background

This works much in the same way as [Export Posts](export-posts.md). However, because the data isn't very dense it does not rely on `XMLHttpRequest`, rather it fetches all the terms and generates the JSON in one request.

### Sample JSON

[Download Sample](samples/nine3-export-terms-sample.json)

```json
[
    {
        "term_id": 73,
        "name": "In The Media",
        "slug": "in-the-media",
        "description": "",
        "taxonomy": "insight-type",
        "parent": "News",
        "nine3_meta": {
            "insight_icon": "",
            "_insight_icon": "field_607d3eae9591a"
        }
    },
    {
        "term_id": 21,
        "name": "Mashup",
        "slug": "mashup",
        "description": "",
        "taxonomy": "insight-type"
    },
    {
        "term_id": 5,
        "name": "News",
        "slug": "news",
        "description": "",
        "taxonomy": "insight-type",
        "nine3_meta": {
            "insight_icon": "310",
            "_insight_icon": "field_607d3eae9591a"
        }
    },
    {
        "term_id": 72,
        "name": "Press Release",
        "slug": "press-release",
        "description": "",
        "taxonomy": "insight-type",
        "parent": "News",
        "nine3_meta": {
            "insight_icon": "",
            "_insight_icon": "field_607d3eae9591a"
        }
    }
]
```