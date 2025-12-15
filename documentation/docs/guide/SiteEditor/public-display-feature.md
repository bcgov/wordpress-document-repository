# Document Post Type (Documents)

The **Documents** post type stores repository documents with custom metadata and taxonomy terms.

---

## Prerequisites

- **Document Repository** plugin installed and enabled.
- Admins or users with edit capability can manage Documents.

---

## Location

In WordPress Admin: **Documents** (left nav) → **All Documents**.

![Documents List View](/images/document-post-type-list-view.png)

---

## What you can do

- Add New Documents: this adds a new document **Post** only. (see note below)
- View and filter taxonomy columns (e.g., **Numbers**, **Zoo**).
- Edit title, excerpt, and metadata fields.
- Add, edit, or remove taxonomy terms.
- Search, filter, trash, restore, or delete Documents.

> Note: To add a PDF document, use the Document Repository Feature

---

## Filter taxonomy columns

- Custom taxonomies appear as columns (e.g., **Numbers**, **Zoo**) for quick scanning.
- Columns can be sorted and filtered (where supported).

_Filtering Documents By Taxonomy Term:_
![Documents List View](/images/public-display-filter.gif)

---

## Edit Document metadata

1. Go to **Documents → All Documents**.
2. Hover and click **Edit** (or **Quick Edit**, if available).
3. Update title, excerpt, and metadata fields.
4. Click **Update**.

_Edit Document Metadata:_
![Edit Document Metadata](/images/public-display-edit-document.gif)

---

## Manage taxonomy terms

1. Go to **Documents → {Taxonomy}** (e.g., Category, Zoo, Numbers).
2. **Add New Term**: enter `Name`, `Slug`, `Description`, then click **Add New {term}**.
3. **Edit Term**: click a term, update `Name`, `Slug`, `Description`, then click **Update** (or **Delete** to remove).

_Edit Metadata Columns:_
![Edit Document Taxonomy](/images/public-display-edit-taxonomy.gif)

---

## Trash, restore, delete

1. In **All Documents**, select items and choose **Move to Trash**, or click **Trash** per row.
2. In **Trash**, choose **Restore** or **Delete Permanently**.

_Trash & restore:_
![Trash and Restore Flow](/images/public-display-trash.gif)

---

## Notes

- Documents are publicly queryable unless trashed.
- Document attachments are hidden from the global Media Library.
- Configure **Metadata Settings** to choose which taxonomy columns appear (e.g., **Numbers**, **Zoo**).
