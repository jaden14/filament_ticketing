<?php

namespace App\Filament\Resources\Requests;

use App\Filament\Resources\Requests\Pages\CreateRequest;
use App\Filament\Resources\Requests\Pages\EditRequest;
use App\Filament\Resources\Requests\Pages\ListRequests;
use App\Filament\Resources\Requests\Pages\ViewAssignment;
use App\Filament\Resources\Requests\Pages\ViewRequestPage;
use App\Filament\Resources\Requests\Schemas\RequestForm;
use App\Filament\Resources\Requests\Tables\RequestsTable;
use App\Models\Request;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'request';

    public static function form(Schema $schema): Schema
    {
        return RequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequestsTable::configure($table);
    }

    /*public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Super Admin: See ALL requests
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Admin: See ALL requests
        if ($user->hasRole('admin')) {
            return $query;
        }

        // Regular Users: See only requests they created OR assigned to them
        if ($user->hasRole('users')) {
            return $query->where(function (Builder $query) use ($user) {
                $query->where('users_id', $user->id) // Requests they created
                      ->orWhereHas('checkrequest', function (Builder $query) use ($user) {
                          $query->where('user_id', $user->id); // Requests assigned to them
                      });
            });
        }

        // Default: If no role matches, show only their own
        return $query->where(function (Builder $query) use ($user) {
            $query->where('users_id', $user->id)
                  ->orWhereHas('checkrequest', function (Builder $query) use ($user) {
                      $query->where('user_id', $user->id);
                  });
        });
    }*/

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'), 
            'my-assignment' => ViewAssignment::route('/{record}/my-assignment'),
            'view-request' =>  ViewRequestPage::route('/{record}/view-request'),
            'eidt' => EditRequest::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
