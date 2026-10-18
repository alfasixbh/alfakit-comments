<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Actions;

/**
 * `CommentsAction` for table rows: an icon button opening the row's comments.
 *
 * ```php
 * $table->recordActions([CommentsTableAction::make()]);
 * ```
 */
class CommentsTableAction extends CommentsAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->iconButton();
        $this->tooltip(__('alfakit-comments::comments.label'));
    }
}
