#!/usr/bin/env bash
#
# One-off: add historical releases to the WordPress.org SVN repository.
#
# 2.0.0, 2.0.1 and 2.2.0 were tagged on GitHub but never deployed, so
# plugins.svn.wordpress.org/push-syndication/ has only tags/1.0. This backfills
# the missing tags so the "Previous versions" dropdown shows a real history.
#
# It writes ONLY to tags/, never to trunk/. WordPress.org serves whatever
# trunk's "Stable tag" points at, so leaving trunk alone means there is no
# window in which an older release becomes the stable download. The current
# release is published the normal way, by .github/workflows/deploy.yml.
#
# Usage:
#   bin/backfill-wporg-tags.sh                 # dry run: show what would happen
#   bin/backfill-wporg-tags.sh --commit        # actually commit to WordPress.org
#
# Credentials come from SVN_USERNAME and SVN_PASSWORD, or svn prompts.

set -euo pipefail

SLUG='push-syndication'
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}/"

# Oldest first. A tag already present on WordPress.org is skipped: wp.org
# treats tags as immutable, and rewriting one is not something to do by accident.
#
# Do NOT add the version you are about to release here. The 10up deploy action
# bails with "Version X was already published" when tags/X exists, so creating
# the tag first would stop it updating trunk — and trunk's Stable tag is what
# WordPress.org actually serves.
TAGS=(2.0.0 2.0.1)

# Repository furniture that was committed inside these old tags but is not part
# of the plugin. assets/ belongs in the SVN repo root, not in a tag.
EXCLUDES=('assets' '.travis.yml' '.github' '.gitignore' '.gitattributes' '.distignore')

COMMIT=false
[[ "${1:-}" == '--commit' ]] && COMMIT=true

REPO_ROOT="$(git rev-parse --show-toplevel)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

AUTH=()
if [[ -n "${SVN_USERNAME:-}" && -n "${SVN_PASSWORD:-}" ]]; then
	AUTH=(--username "$SVN_USERNAME" --password "$SVN_PASSWORD" --no-auth-cache)
fi

echo "==> Checking out ${SVN_URL} (tags only)"
svn checkout "${AUTH[@]}" --depth immediates "$SVN_URL" "$WORK/svn" >/dev/null
svn update "${AUTH[@]}" --set-depth immediates "$WORK/svn/tags" >/dev/null

cd "$WORK/svn"

ADDED=()
for TAG in "${TAGS[@]}"; do
	if [[ -d "tags/$TAG" ]]; then
		echo "==> tags/$TAG already exists on WordPress.org; skipping"
		continue
	fi

	if ! git -C "$REPO_ROOT" rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
		echo "==> No git tag $TAG; skipping" >&2
		continue
	fi

	echo "==> Exporting git tag $TAG into tags/$TAG"
	mkdir -p "tags/$TAG"
	git -C "$REPO_ROOT" archive "$TAG" | tar -x -C "tags/$TAG"

	for EXCLUDE in "${EXCLUDES[@]}"; do
		rm -rf "tags/$TAG/${EXCLUDE:?}"
	done

	svn add "tags/$TAG" >/dev/null
	ADDED+=("$TAG")
done

if [[ ${#ADDED[@]} -eq 0 ]]; then
	echo "==> Nothing to do."
	exit 0
fi

echo
echo "==> Pending changes:"
svn status | head -50
echo "==> $(svn status | grep -c '^A') files to add across: ${ADDED[*]}"
echo

if [[ "$COMMIT" != true ]]; then
	echo "Dry run. Re-run with --commit to publish these tags to WordPress.org."
	exit 0
fi

svn commit "${AUTH[@]}" -m "Backfill historical releases: ${ADDED[*]}"
echo "==> Done. WordPress.org takes up to ~6 hours to surface new tags."
