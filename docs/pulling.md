# Pulling feeds

Pulling imports items from a feed into this site as posts, on a schedule. Each feed is a [site](setting-up-sites.md) with the RSS or XML transport.

## Schedule pulls

1. [Add each feed as a site](setting-up-sites.md#add-a-site), enable it, and put it in a site group.
2. On **Settings → Push Syndication**, under **Site Groups**, tick the site groups to pull.
3. Under **Pull Options**, set **Specify time interval in seconds**: how often each site is pulled. The default is 3600 (an hour), and the shortest allowed is 300 (five minutes).
4. Choose **Pull Now** or **Save Changes**.

Only the site groups ticked here are pulled on a schedule. A feed site in any other site group is never pulled automatically, though you can pull it with [WP-CLI](wp-cli.md#pulling).

Each site gets its own WP-Cron event, repeating at the interval. Saving the settings reschedules every site's event to run straight away, which is all **Pull Now** does. Adding, editing or deleting a site, or creating or deleting a site group, reschedules them a minute later.

Pulls run in the background, so a pull starts on the next cron run after it's due. If you run WP-Cron from a system cron job, pulls happen on that schedule.

## What happens in a pull

For each enabled site, the plugin fetches the feed and turns each item into a post array. Then, for each item:

1. It looks for a post already pulled from the same item, by the item's unique ID (its GUID). Items without a unique ID are skipped and logged. The lookup covers every pulled post, not just those from this site, so two feeds that use the same IDs update each other's posts.
2. **New item:** a post is created, and the item's unique ID and the site's ID are stored on it, in `syn_post_guid` and `syn_source_site_id`.
3. **Item pulled before:** the post is updated, if **Update pulled posts** is ticked under **Pull Options**. Otherwise the item is skipped. Updating replaces the post's fields with the feed's, including its status: if an editor has changed a pulled post's status, the next update puts it back.

A pull doesn't create revisions of pulled posts.

Pulled posts can only be posts, pages, or custom post types with an admin screen. An item that would become any other type, such as a template, a reusable block or a Syndication site, is skipped and logged as `invalid_post_type`. The [`syndication_pull_post_types`](hooks.md#syndication_pull_post_types) filter changes the allowed list.

Pulled posts have no author unless the feed supplies one: on a scheduled pull there's no logged-in user to give them.

Feeds are fetched with the WordPress HTTP API, so proxy settings and HTTP API filters apply. Requests identify themselves as `WordPress/Syndication Plugin`, which you can change with [`syn_pull_user_agent`](hooks.md#syn_pull_user_agent). Feeds on private network addresses are allowed.

When a pull fails, it's retried and, after repeated failures, the site can be disabled. See [Logs and failures](logs-and-failures.md).

## RSS feeds

Each RSS or Atom item becomes a post like this:

| Post field | From |
| --- | --- |
| Title | The item's title |
| Content | The item's content, or its description if it has no content |
| Excerpt | The item's description, or its content if it has no description |
| Date | The item's date. Items with no date get the time of the pull, and keep their date when updated. |
| Unique ID | The item's GUID. If it has none, SimplePie uses its link, its title or a hash of its content, so an item without a GUID whose content changes can be pulled again as a new post. |
| Post type, status, comment status, ping status | The site's settings |
| Categories | The item's categories, if **Select category status** is set to import them (below) |

The item's author, enclosures and other elements aren't imported. To import more, use the [`syn_rss_pull_filter_post`](hooks.md#syn_rss_pull_filter_post) filter, which receives the SimplePie item.

When categories are imported, each of the item's categories is matched by name: first to an existing category, then to an existing tag. A name that matches neither becomes a new category.

## XML feeds

### Mapping an XML feed

An XML site needs to know where each post is in the feed, and which elements hold which fields. You describe both with [XPath](https://developer.mozilla.org/docs/Web/XML/XPath).

- **Enter feed URL:** the feed's address.
- **Select post type**, **Select post status**, **Select comment status** and **Select ping status:** applied to every pulled post.
- **Enter XML namespace:** if the feed's elements are in a namespace, such as an Atom feed's `http://www.w3.org/2005/Atom`, its URI. Then refer to those elements in every XPath with the `syn:` prefix, for example `/syn:feed/syn:entry` and `syn:title`. Prefixes the feed declares itself, such as `media:`, work as they are.
- **Enter XPath to post root:** an XPath that matches each item in the feed, for example `//item`. Each match becomes one post.
- **Enter post meta key for unique post identifier:** the custom field (from your mappings below) that holds each item's unique ID. If you leave it blank, map an element to the `post_guid` field instead. Items without a unique ID are skipped.
- **Enter parent element for enclosures**, **Enter meta name for enclosures** and **Enclosure is an image file:** see [Enclosures](#enclosures).
- **Select category/categories:** categories given to every pulled post.

Under **XPath-to-Data Mapping**, add a row for each piece of data:

- **XPath Expression:** where the value is. To set a fixed value instead, write `string(…)`: `string(News)` gives the value `News`.
- **Item:** tick this when the XPath is relative to each item (the post root). Leave it unticked for a value from elsewhere in the feed, such as the channel title; that value is applied to every item.
- **Enc.:** tick this for an enclosure field.
- **Meta:** tick this to store the value in the custom field named in **Field in Post**.
- **Tax:** tick this to add the values as terms in the taxonomy named in **Field in Post**. Every matching element becomes a term.
- **Field in Post:** the post field, custom field or taxonomy the value goes into. With neither **Meta** nor **Tax** ticked, it's a post field such as `post_title`, `post_content`, `post_excerpt`, `post_date` or `post_author`.

For example, for a feed of `<item>` elements:

| XPath Expression | Item | Meta | Tax | Field in Post |
| --- | --- | --- | --- | --- |
| `title` | Yes | | | `post_title` |
| `body` | Yes | | | `post_content` |
| `@id` | Yes | Yes | | `source_id` |
| `subject` | Yes | | Yes | `post_tag` |
| `string(Wire)` | | Yes | | `source_name` |

with **Enter XPath to post root** set to `//item` and **Enter post meta key for unique post identifier** set to `source_id`.

How conflicts are resolved:

- **Post fields:** when a feed-level value (a row without **Item**) and an item's own value set the same field, the item's value wins.
- **Custom fields and taxonomies:** the feed-level value wins.

Dates mapped to `post_date` or `post_date_gmt` must be in `YYYY-MM-DD HH:MM:SS` form; other formats, such as RSS's `pubDate`, make the item fail to save. Convert them with the [`syn_pull_new_post`](hooks.md#syn_pull_new_post) and [`syn_pull_edit_post`](hooks.md#syn_pull_edit_post) filters.

When an XPath matches nothing for an item, that field is left out: a new post gets WordPress's default, and an updated post keeps its current value. An XPath that isn't valid stops the pull, and the error names the expression.

Some fields can't be mapped:

- **Custom fields:** keys starting with an underscore, which are protected, keys starting with `syn_` or `_syn_`, which the plugin uses itself, and keys with characters outside printable ASCII.
- **Post fields:** `ID` and `import_id`, which would let the feed choose which existing post to overwrite.

If you save a mapping like that, it's dropped and the settings screen lists it.

Every post pulled from an XML feed also gets these custom fields: `is_update` (the time of the pull), `position` (the item's position in the feed, unless mapped), and `enc_field` (the name set in **Enter meta name for enclosures**, if any).

### Enclosures

Enclosures are repeated elements inside each item, such as photos. To import them:

1. Set **Enter parent element for enclosures** to an XPath, relative to each item, that matches each enclosure element.
2. Add a mapping row for each enclosure field, with **Enc.** ticked and **Item** unticked. Its XPath is relative to the enclosure element.
3. Tick **Enclosure is an image file** if the enclosures are images. Each enclosure then always has `caption`, `credit`, `description`, `url`, `width`, `height` and `position` keys.

Where the enclosures are stored depends on **Enter meta name for enclosures**:

- **Set:** each enclosure is stored as a separate value of the custom field it names, replacing that field's values on every update. Define `ENCLOSURES_AS_STRINGS` as `true` to store each enclosure as one string, its values separated by line breaks, rather than as an array.
- **Blank:** all of an item's enclosures are stored together, as one array, in its `enclosures` custom field.

## Pulling now

To pull a site or a site group immediately, from the shell, use [`wp syndication pull_site` or `wp syndication pull_sitegroup`](wp-cli.md#pulling).
