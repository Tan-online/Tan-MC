<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-3xl">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Workspace Summary</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Team and wage month visibility for your operations workspace.</p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Team</div>
                            <div class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $teamName }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Active Wage Month</div>
                            <div class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $activeWageMonthLabel }}</div>
                        </div>
                    </div>

                    <form method="get" action="{{ route('profile.edit') }}" class="mt-6">
                        <label for="workspace-month" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Wage Month Selector</label>
                        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="w-full sm:max-w-xs">
                                <select id="workspace-month" name="month" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    @foreach ($wageMonthOptions as $option)
                                        <option value="{{ $option['value'] }}" @selected($selectedMonthKey === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Set Wage Month
                            </button>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Selected Wage Month: {{ $selectedMonthLabel }}</p>
                    </form>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
