<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequests extends ListRecords
{
    protected static string $resource = RequestResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->has('tableAction')) {
            $this->js("
                setTimeout(() => {
                    const url = new URL(window.location);
                    url.searchParams.delete('tableAction');
                    url.searchParams.delete('tableActionRecord');
                    window.history.replaceState({}, document.title, url.pathname);
                }, 500);
            ");
        }
    }
    
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(function ($record) {
                    return redirect()->to(
                        RequestResource::getUrl('index', [
                            'tableAction' => 'assignReassign',
                            'tableActionRecord' => $record->id,
                        ])
                    );
                }),
        ];
    }
}
