<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                   Select::make('classification')
                            ->label('Classification')
                            ->options([
                                'Application Development Services' => 'Application Development Services',
                                'Application Managed Services' => 'Application Managed Services',
                                'Communication Services' => 'Communication Services',
                                'Connectivity Management Services' => 'Connectivity Management Services',
                                'Equipment/Tool Borrowing' => 'Equipment/Tool Borrowing',
                                'ICT Support Repair' => 'ICT Support Repair',
                                'Other Technical Services' => 'Other Technical Services',
                                'Preventive Maintenance' => 'Preventive Maintenance',
                            ])
                            ->required(),
                    Select::make('category')
                            ->options([
                                '1' => 'Request',
                                '0' => 'Booking'
                            ])
                            ->required(),
                    TextInput::make('service_type')
                            ->required(),
                    Toggle::make('is_active')
                                    ->onIcon(Heroicon::Bolt)
                                    ->offIcon(Heroicon::ServerStack)
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->inline(false)
                                    ->helperText('Toggle to activate or deactivate')
                                    ->required(),
            ]);
    }
}
