<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Resources;

use AlfaSix\Alfakit\Forms\Components\TextInput;
use AlfaSix\Alfakit\Infolists\Components\TextEntry;
use AlfaSix\Alfakit\Resources\Resource;
use AlfaSix\Alfakit\Schemas\Components\Section;
use AlfaSix\Alfakit\Schemas\Schema;
use AlfaSix\Alfakit\Tables\Columns\TextColumn;
use AlfaSix\Alfakit\Tables\Table;
use AlfaSix\AlfakitComments\Actions\CommentsTableAction;
use AlfaSix\AlfakitComments\Infolists\Components\CommentsEntry;
use AlfaSix\AlfakitComments\Tests\Fixtures\Models\Ticket;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $slug = 'tickets';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            CommentsEntry::make()->hiddenOn('create'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title'),
            Section::make('Comments')->schema([
                CommentsEntry::make(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('title')])
            ->recordActions([CommentsTableAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => TicketResource\Pages\ListTickets::route('/'),
            'view' => TicketResource\Pages\ViewTicket::route('/{record}'),
            'edit' => TicketResource\Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
