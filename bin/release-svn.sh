#!/usr/bin/env bash
#
# release-svn.sh — sync Leaky Paywall core into the WordPress.org SVN working
# copy and stage the release tag, using .distignore to decide what ships.
#
# This collapses the old manual "rsync -> svn add/rm -> svn cp" steps into one
# command. It deliberately DOES NOT commit: it stops before `svn ci` so the
# irreversible publish stays a manual step (see the lp-release skill, gate 3).
#
# Usage:   bin/release-svn.sh X.Y.Z
# Env:     LP_SVN_DIR   override the SVN working copy (default ~/Documents/svn/leaky-paywall)
#
set -euo pipefail

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
	echo "usage: bin/release-svn.sh X.Y.Z" >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SVN_DIR="${LP_SVN_DIR:-$HOME/Documents/svn/leaky-paywall}"
DISTIGNORE="$PLUGIN_DIR/.distignore"

# --- Preconditions -----------------------------------------------------------
[[ -f "$DISTIGNORE" ]]      || { echo "Missing $DISTIGNORE" >&2; exit 1; }
[[ -d "$SVN_DIR/trunk" ]]   || { echo "SVN trunk not found at $SVN_DIR/trunk" >&2; exit 1; }
# vendor/ is git-ignored but MUST ship (Action Scheduler is required at runtime).
if [[ ! -f "$PLUGIN_DIR/vendor/woocommerce/action-scheduler/action-scheduler.php" ]]; then
	echo "vendor/ is missing Action Scheduler. Run 'composer install' before releasing." >&2
	exit 1
fi
# include/stripe is a git submodule (stripe-php) and MUST ship populated.
if [[ ! -f "$PLUGIN_DIR/include/stripe/init.php" ]]; then
	echo "include/stripe is empty. Run 'git submodule update --init' before releasing." >&2
	exit 1
fi

echo "Plugin:  $PLUGIN_DIR"
echo "SVN:     $SVN_DIR"
echo "Version: $VERSION"
echo

# --- 1. Mirror source into trunk, honoring .distignore -----------------------
# --exclude='.svn' protects SVN metadata in the destination from --delete.
rsync -a --delete \
	--exclude='.svn' \
	--exclude-from="$DISTIGNORE" \
	"$PLUGIN_DIR"/ "$SVN_DIR"/trunk/

# --- 2. Stage adds and removes in SVN ---------------------------------------
cd "$SVN_DIR"
# Add every new unversioned file (--force lets `add` run on the versioned root).
svn add --force . -q
# Remove files deleted from source since the last release (status '!').
svn status | awk '/^!/ {print $2}' | while IFS= read -r f; do
	[[ -n "$f" ]] && svn rm "$f" -q
done

# --- 3. Tag ------------------------------------------------------------------
# A tag directory that already exists means one of two very different things,
# and treating them the same nearly shipped a tag that did not match trunk:
#
#   Schedule: add     -> staged but NOT committed, from an earlier run of this
#                        script in the same release. It predates the trunk sync
#                        above, so it is stale. Discard and re-copy.
#   Schedule: normal  -> already published to WordPress.org. Never rewrite it.
#
TAG_SCHEDULE="$(svn info "tags/$VERSION" 2>/dev/null | awk -F': ' '/^Schedule:/ {print $2}' || true)"

if [[ "$TAG_SCHEDULE" == "normal" ]]; then
	echo "tags/$VERSION is already committed to WordPress.org." >&2
	echo "Refusing to rewrite a published tag. Release a new version instead." >&2
	exit 1
fi

if [[ -d "tags/$VERSION" ]]; then
	echo "tags/$VERSION was staged by an earlier run and predates this sync. Refreshing it."
	svn revert -R "tags/$VERSION" -q 2>/dev/null || true
	rm -rf "tags/$VERSION"
fi

svn cp trunk "tags/$VERSION"

# The tag must be byte-identical to what we just staged in trunk.
if ! diff -rq --exclude=.svn trunk "tags/$VERSION" >/dev/null 2>&1; then
	echo "tags/$VERSION does not match trunk after copy. Aborting." >&2
	exit 1
fi

# --- Done: leave the commit to a human --------------------------------------
echo
echo "Staged trunk + tags/$VERSION. Review the working copy first:"
echo "    ( cd \"$SVN_DIR\" && svn status )"
echo
echo "Then PUBLISH (irreversible — publishes to every install):"
echo "    ( cd \"$SVN_DIR\" && svn ci -m \"tagging version $VERSION\" --username zeen101 )"
