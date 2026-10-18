# Changelog

All notable changes to `alfasix/alfakit-comments` are documented in this file.

## Unreleased

- Initial release: `Comment` and `CommentReaction` models with a publishable migration, the `HasComments` trait and `Commentable` / `Commenter` contracts (`IsCommenter` defaults from the panel's name and avatar providers), `CommentsEntry` (composer with a `RichEditor` and `@` mentions, reactions, edit / delete for authors, "Load more"), `CommentsAction` (slide-over), `CommentsTableAction`, a polling `CommentsWidget`, mention database notifications, `CommentWasCreated` / `UserWasMentioned` / `CommentWasReacted` events, an overridable `CommentPolicy`, tenant-scoped mentions and sanitised bodies.
