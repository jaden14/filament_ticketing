<div class="min-h-screen bg-gradient-to-br from-orange-300 to-orange-500 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-gray-900 rounded-lg shadow-xl overflow-hidden">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-white mb-6">Request Form</h2>
                
                <form wire:submit="submit" class="space-y-6">
                    {{ $this->form }}
                    
                    <div class="flex justify-start pt-4">
                        <button 
                            type="submit" 
                            class="inline-flex items-center px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg shadow-md transition duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <x-filament-actions::modals />
</div>