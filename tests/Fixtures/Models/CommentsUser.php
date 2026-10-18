<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Models;

use AlfaSix\Alfakit\Tests\Fixtures\Models\TenantUser;
use AlfaSix\AlfakitComments\Concerns\IsCommenter;
use AlfaSix\AlfakitComments\Contracts\Commenter;

class CommentsUser extends TenantUser implements Commenter
{
    use IsCommenter;

    protected $table = 'users';
}
