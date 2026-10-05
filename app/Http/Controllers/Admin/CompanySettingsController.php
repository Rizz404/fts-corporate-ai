<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanySettingsRequest;
use App\Models\CompanySetting;
use App\Modules\Experience\Support\CompanySettings;
use App\Modules\Identity\Actions\WriteAuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompanySettingsController extends Controller
{
    public function edit(CompanySettings $settings): View
    {
        $current = $settings->get();
        Gate::authorize('update', $current);

        return view('admin.settings.edit', ['settings' => $current]);
    }

    public function update(CompanySettingsRequest $request, CompanySettings $settings, WriteAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit) {
            /** @var CompanySetting $row */
            $row = CompanySetting::query()->lockForUpdate()->find(CompanySetting::SINGLETON_ID)
                ?? tap(new CompanySetting(CompanySetting::defaults()), fn ($row) => $row->id = CompanySetting::SINGLETON_ID);

            $welcome = [];
            foreach (config('corporate.locales') as $locale) {
                $welcome[$locale] = trim((string) $request->validated("welcome_text.{$locale}", ''));
            }

            $changed = array_keys(array_filter([
                'brand_name' => $row->brand_name !== $request->validated('brand_name'),
                'welcome_text' => $row->welcome_text !== $welcome,
            ]));

            $row->fill([
                'brand_name' => trim($request->validated('brand_name')),
                'welcome_text' => $welcome,
                'updated_by' => $request->user()->id,
            ])->save();

            if ($changed !== []) {
                $audit->handle($request->user(), 'company_settings.updated', $row, ['fields' => $changed]);
            }
        });

        $settings->forget();

        return redirect()->route('admin.settings.edit')->with('status', __('ui.settings.updated'));
    }
}
