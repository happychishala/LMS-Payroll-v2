<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationGroup = 'IT Support';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Tickets';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->whereIn('status', ['open', 'in_progress'])->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Ticket Details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('ticket_number')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?SupportTicket $record): bool => filled($record)),
                    Forms\Components\Select::make('submitted_by_id')
                        ->label('Submitted By')
                        ->relationship('submittedBy', 'name')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?SupportTicket $record): bool => filled($record)),
                    Forms\Components\TextInput::make('subject')
                        ->required()
                        ->maxLength(125)
                        ->columnSpanFull()
                        ->disabled(fn (?SupportTicket $record): bool => filled($record) && ! static::canManageTickets()),
                    Forms\Components\Select::make('category')
                        ->options(static::categoryOptions())
                        ->default('general')
                        ->required()
                        ->disabled(fn (?SupportTicket $record): bool => filled($record) && ! static::canManageTickets()),
                    Forms\Components\Select::make('priority')
                        ->options(static::priorityOptions())
                        ->default('normal')
                        ->required()
                        ->disabled(fn (?SupportTicket $record): bool => filled($record) && ! static::canManageTickets()),
                    Forms\Components\Textarea::make('description')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull()
                        ->disabled(fn (?SupportTicket $record): bool => filled($record) && ! static::canManageTickets()),
                ]),
            Forms\Components\Section::make('IT Work')
                ->columns(2)
                ->visible(fn (): bool => static::canManageTickets())
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(static::statusOptions())
                        ->default('open')
                        ->required(),
                    Forms\Components\Select::make('assigned_to_id')
                        ->label('Assigned To')
                        ->options(fn (): array => static::itUserOptions())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('resolution_notes')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Ticket')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(45),
                Tables\Columns\TextColumn::make('submittedBy.name')
                    ->label('Submitted By')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category')
                    ->formatStateUsing(fn (?string $state): string => static::formatOptionLabel($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('priority')
                    ->formatStateUsing(fn (?string $state): string => static::formatOptionLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        'low' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (?string $state): string => static::formatOptionLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'waiting_on_user' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(static::priorityOptions()),
                Tables\Filters\SelectFilter::make('category')
                    ->options(static::categoryOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => static::canManageTickets()),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::canManageTickets()) {
            return $query;
        }

        return $query->where('submitted_by_id', auth()->id());
    }

    public static function canManageTickets(): bool
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'getRoleNames')) {
            return false;
        }

        $roles = $user->getRoleNames()
            ->map(fn (string $role): string => strtolower($role))
            ->all();

        return collect(config('support_tickets.it_roles', []))
            ->map(fn (string $role): string => strtolower($role))
            ->contains(fn (string $role): bool => in_array($role, $roles, true));
    }

    public static function itUserOptions(): array
    {
        $roles = collect(config('support_tickets.it_roles', []))
            ->filter()
            ->values()
            ->all();

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function statusOptions(): array
    {
        return [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'waiting_on_user' => 'Waiting On User',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'general' => 'General',
            'hardware' => 'Hardware',
            'software' => 'Software',
            'network' => 'Network',
            'account_access' => 'Account Access',
            'lms_issue' => 'LMS Issue',
        ];
    }

    public static function formatOptionLabel(?string $value): string
    {
        return str((string) $value)
            ->replace('_', ' ')
            ->title()
            ->value();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
