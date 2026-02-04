<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use App\Models\Request as RequestModel; // Your model
use App\Models\User;
use App\Models\Office;
use Filament\Notifications\Notification;

class PublicRequestForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    TextInput::make('cats_no')
                        ->label('CATS No.'),
                        
                    TextInput::make('name')
                        ->label('Employee Name')
                        ->required(),
                ]),
                
            Select::make('office_id')
                ->label('Office')
                ->options(Office::query()->orderBy('officename')->pluck('officename', 'id'))
                ->searchable()
                ->required(),
                
            Textarea::make('remarks')
                ->label('Problem/Issue')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            hidden::make('status')
                ->default("Pending"),
        ];
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public function submit(): void
    {
        try {
        // Validate the form first
        $data = $this->form->getState();
        
        // Create the request
        $request = RequestModel::create($data);
        
        Notification::make()
            ->title('Request Submitted Successfully')
            ->body('Your request has been submitted with ID: ' . $request->id)
            ->success()
            ->send();
            
        // Reset form
        $this->reset('data');
        $this->form->fill();
        
        } catch (\Exception $e) {
            
            Notification::make()
                ->title('Error')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
            }
    }

    public function render()
    {
        return view('livewire.public-request-form')
                ->layout('layouts.public');
    }
}
