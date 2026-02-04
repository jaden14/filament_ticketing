<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class OfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('officename')
                            ->label("name")
                            ->required(),
            ]);
    }
}
