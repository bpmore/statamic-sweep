# Using Sweep

Sweep finds text across the site and replaces it, after showing you every
change. This guide is for the people who run sweeps. Nothing here needs a
developer.

Open it from **Tools → Sweep** in the control panel. If it is not there, ask
whoever manages roles for the **Use Sweep** permission.

## How a sweep works

1. **Search.** Say what to find, what to put in its place, and where to look.
2. **Preview.** Sweep lists every match it found, with the words around it.
   Nothing on the site has changed yet.
3. **Choose.** Leave out any match, or any whole item, you do not want
   changed.
4. **Apply.** Sweep writes the changes you kept.
5. **Undo, if you need to.** For 30 days, one button puts the old text back.

## Searching

### Find and Replace with

Type the text to find and the text to put in its place. Leave **Replace with**
empty to remove the text.

### Match

- **Exact text** finds the text exactly as you typed it, wherever it is,
  including inside longer words. "cat" finds "cat", "catalog" and "bobcat".
- **Whole words** finds it only where it stands as a word. "cat" finds "cat"
  and "cat." but not "catalog".
- **Regular expression** is a pattern, for people who know them. See
  [Regular expressions](#regular-expressions) below.

**Match case** is on by default: "Acme" does not find "acme". Turn it off to
find every case.

**Keep the case of what is replaced** is for renaming something. With "Acme"
replaced by "Apex", "Acme" becomes "Apex", "ACME" becomes "APEX" and "acme"
becomes "apex". Turn **Match case** off when you use it, so all three are
found. A match in some other mix of capitals ("AcMe") gets the replacement
exactly as you typed it.

### Where to look

Tick the collections, taxonomies, globals, navigation menus and asset
containers to search. **Select all** and **Select none** save clicking.

**Sites:** leave every site unticked to search them all. Tick some to search
only those. Asset alt text and other asset fields are shared by every site,
so a search limited to some sites leaves assets out.

**Entry status:** leave every status unticked to search entries of any
status, or tick the ones you want: published, draft, scheduled or expired.

**Only these fields:** type field handles separated by commas, such as
`title, content`, to search only those fields. Leave it empty to search every
text field.

**Also search code fields** and **Also search slugs** are off unless you tick
them. A slug is part of a page's address: changing it changes the URL, and
links to the old address stop working unless someone adds a redirect. Sweep
does not add redirects.

### Presets

**Start from a preset** fills in the search for you. Three come with Sweep:

- **Domain change** moves links from an old address to a new one, and from
  `http` to `https`. Edit both addresses before you preview.
- **WordPress shortcode cleanup** removes leftover `[caption]`, `[embed]` and
  `[gallery]` shortcodes and keeps the text between them.
- **Rebrand** changes one name to another as a whole word, keeping its case.
  Edit both names before you preview.

A preset only fills in the search. You still choose where to look.

If you have the **Manage presets** permission, **Save as preset** saves the
search on screen under a title. The **Presets** screen lists them all. Use
**Download YAML** to send one to another site, and paste one into **Import a
preset** to bring one in.

## Reading the preview

When the search finishes, the summary shows:

- **Items searched**: how many things Sweep looked in.
- **Items matched** and **Matches**: what it found.
- **Split matches (edit by hand)**: see below.
- **Items you may not edit (not searched)**: things in your search area you do
  not have permission to edit. Sweep did not look in them, and they never
  appear in your results.

Below that, each item that matched has its own card, with a link to edit it.
Under each field, every match is shown with the words around it: the old text
struck through, the new text underlined.

**Leaving things out.** Untick **Include this item** to leave a whole item out,
or untick a single match. Your choices are saved as you make them. A left-out
match says "Left out:" in front of it.

**Split matches.** When the text you are looking for runs across two kinds of
formatting, for example "Acme Corporation" where only "Acme" is a link, or
half a phrase is bold, Sweep does not change it. Changing it would mean
deciding which formatting the new words get, and guessing wrong would break
the page. These are listed under **Split matches, edit by hand**, with a link
to the item.

A large site's search runs in the background. You can watch the counts go up,
and results appear as they are found.

## Applying

Press **Apply changes**, then **Yes, apply**. You need the **Apply sweeps**
permission.

Sweep checks each item again just before changing it. It skips, and tells
you, any item that:

- **Changed since preview**: someone edited it after you searched. Search
  again to include it.
- **Has unpublished changes**: it has a draft waiting to be published, which
  would put the old text back when it is published. Edit it by hand.
- **No longer there**: it was deleted.
- **Not yours to edit**: your permissions changed since you searched.

Everything else is saved the way an editor's save is: the site's search index
and cached pages are updated, and if the collection keeps revisions, a
revision is saved with the message `Sweep: <find> → <replace>`. If the site
saves content changes to Git, the whole sweep is one commit.

## Undoing

Press **Undo sweep**, then **Yes, undo**. You need the **Undo sweeps**
permission, and the sweep must be under 30 days old.

Undo puts back exactly the text that was there before, in every field nobody
has edited since. A field someone has edited since the sweep is left as it is
and listed as **Edited since the sweep, so not put back**, because putting the
old text back would throw their work away. When that happens, the sweep is
marked **Partly undone**.

After 30 days the list of changes is removed and the sweep can no longer be
undone. It stays in the history.

## History

**Sweep history** lists every sweep: when, who, what was found and replaced,
where, how much, and where it stands (previewed, applied, undone, partly
undone, failed). Open your own sweeps from there. Everyone's are listed, but
only the person who started a sweep, and super admins, can open it, because
it shows content that person may edit.

**Search again** fills in the search screen with an earlier sweep's search.

## Regular expressions

Choose **Regular expression** under **Match** to find a pattern. Type the
pattern on its own, without slashes around it.

In **Replace with**, `$1`, `$2` and so on put back what each group in round
brackets matched, and `${name}` a named group. For example, find
`(\d{3})-(\d{4})` and replace with `$1.$2` to turn `555-1234` into
`555.1234`.

A few things to know:

- `$10` means group ten. To put group one followed by a 0, write `${1}0`.
- A pattern that can match nothing at all, like `x*` or `^`, never adds text
  where it matched nothing. Only real matches are changed.
- A pattern with a mistake is refused before anything is searched, with what
  is wrong with it.
- In **Exact text** and **Whole words**, `$1` is just the characters `$1`.

## What Sweep never changes

- Handles, ids and dates.
- Select, radio, checkbox and button group values (they are keys, not text).
- Links to entries and assets, and relationship fields.
- Bard formatting, set types and anything that is not text.
- A Bard field stored as HTML.
- File names and paths of assets.
- Templates, config files or anything outside the site's content.
