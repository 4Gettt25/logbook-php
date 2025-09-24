<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use App\Models\Agent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationGroup = 'System Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Agent Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('hostname')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('ip_address')
                            ->required()
                            ->ip(),
                        Forms\Components\Select::make('environment')
                            ->options([
                                'development' => 'Development',
                                'staging' => 'Staging',
                                'production' => 'Production',
                            ])
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'error' => 'Error',
                                'pending' => 'Pending',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('System Information')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('architecture')
                            ->maxLength(50),
                        Forms\Components\KeyValue::make('os_info')
                            ->keyLabel('Property')
                            ->valueLabel('Value'),
                    ])->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\Textarea::make('tls_certificate')
                            ->rows(5),
                        Forms\Components\KeyValue::make('telegraf_config')
                            ->keyLabel('Setting')
                            ->valueLabel('Value'),
                        Forms\Components\KeyValue::make('metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                    ]),

                Forms\Components\Section::make('System Fields')
                    ->schema([
                        Forms\Components\TextInput::make('api_token')
                            ->disabled()
                            ->visibleOn('edit'),
                        Forms\Components\DateTimePicker::make('last_heartbeat')
                            ->disabled()
                            ->visibleOn('edit'),
                    ])->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hostname')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('environment')
                    ->colors([
                        'danger' => 'production',
                        'warning' => 'staging',
                        'success' => 'development',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'pending',
                        'danger' => 'error',
                        'secondary' => 'inactive',
                    ]),
                Tables\Columns\IconColumn::make('is_online')
                    ->boolean()
                    ->getStateUsing(fn (Agent $record): bool => $record->isOnline())
                    ->label('Online'),
                Tables\Columns\TextColumn::make('last_heartbeat')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\TextColumn::make('logs_count')
                    ->counts('logs')
                    ->label('Logs'),
                Tables\Columns\TextColumn::make('metrics_count')
                    ->counts('metrics')
                    ->label('Metrics'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('environment')
                    ->options([
                        'development' => 'Development',
                        'staging' => 'Staging',
                        'production' => 'Production',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'error' => 'Error',
                        'pending' => 'Pending',
                    ]),
                Tables\Filters\Filter::make('online')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('last_heartbeat', '>=', now()->subMinutes(5))
                    )
                    ->label('Online Only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('regenerate_token')
                    ->icon('heroicon-o-key')
                    ->action(function (Agent $record) {
                        $record->update([
                            'api_token' => \Illuminate\Support\Str::random(64)
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalDescription('This will generate a new API token for this agent. The old token will no longer work.'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('activate')
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'active']))
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('deactivate')
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'inactive']))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('last_heartbeat', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'view' => Pages\ViewAgent::route('/{record}'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['logs', 'metrics']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'hostname', 'ip_address'];
    }
}