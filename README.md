# GitHub Pages Plugin for OJS 3.5

Publish Markdown ('.md') files hosted on GitHub as pages in OJS – either for a
single journal **or for the whole site**, managed directly from the OJS
administration.

It works like the **Static Pages** plugin, but instead of authoring content in
the editor you point each page at a '.md' file on GitHub. The plugin downloads
the file, renders it to GitHub-flavored HTML, **rewrites relative image and link
URLs to absolute GitHub URLs** (so images stored in another folder of the
repository show up correctly), sanitizes the result, caches it in the database
and serves it at a path you choose.

## Requirements

* OJS 3.5.x

## Installation

1. Copy this folder to 'plugins/generic/githubPages' in your OJS installation
   (the folder name must be 'githubPages').
2. Run php tools/upgrade.php upgrade
3. Enable the plugin (see the two levels below).

## Two levels of use

**Per journal (Journal Manager).** Inside a journal go to
**Settings → Website → Plugins**, enable *GitHub Pages Plugin*, then open the
**GitHub Pages** tab on the Website settings page. Pages are served under that
journal, e.g. 'https://host/index.php/<journal>/<path>'.

**Site-wide (Administrator).** In **Administration → Site Settings → Plugins**,
enable *GitHub Pages Plugin* site-wide, then use the **GitHub Pages** tab on the
Site Settings page. These pages are served under the site (index) context, e.g.
'https://host/index.php/index/<path>'. Site-wide pages have no journal context
('context_id' is NULL) and are kept separate from each journal's pages.

The same gear **Settings** modal (optional GitHub token) is available at both
levels.

## Usage

1. Click **Add Page** and fill in:
   * **Path** – the URL slug, e.g. 'about' or 'docs/guide'.
   * **Title** – shown as the page heading.
   * **GitHub Markdown URL** – a link to a public '.md' file. Both the normal
     blob link and the 'raw.githubusercontent.com' link are accepted.
2. Save. The content is fetched and rendered immediately, and the page becomes
   available at the path shown in the form.
3. When the source file changes on GitHub, use **Refresh from GitHub** on the
   page row to pull the latest version.

The title and source URL are multilingual: each language can point at a
different '.md' file.

## Optional: access token

Public repositories need no authentication. If you hit GitHub's rate limit
(60 requests/hour per IP unauthenticated), add a GitHub personal access token in
the plugin's **Settings** modal to raise it to 5000/hour. A read-only
fine-grained token with no extra permissions is enough. The token is stored per
level (journal or site).

## How rendering works

1. The raw Markdown is downloaded from 'raw.githubusercontent.com'.
2. It is rendered to HTML via the GitHub Markdown API ('POST /markdown', GFM
   mode), so tables, task lists, fenced code, etc. match GitHub.
3. Relative 'src'/'href' values are resolved against the source file's folder
   and rewritten to absolute URLs – images point at the raw host, links point at
   the browsable 'blob' view.
4. The HTML is sanitized (scripts, event handlers and other unsafe markup are
   removed) and stored to the database.

## Limitations

* The content is a snapshot; it updates when you save or click *Refresh*, not
  automatically on every GitHub commit.
* Each page maps to one '.md' file; separate files are not stitched into a site.

## License

GNU GPL v3. See 'LICENSE'.
