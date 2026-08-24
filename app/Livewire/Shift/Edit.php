<?php

declare(strict_types=1);

namespace App\Livewire\Shift;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\ShiftCategory;
use Flux\Flux;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Edit extends Component
{
    #[Locked]
    public Plan $plan;

    #[Locked]
    public Shift $shift;

    #[Validate('required|string')]
    public string $title = '';

    #[Validate('nullable|string|max:10000')]
    public string $description = '';

    #[Validate('required|date')]
    public string $start = '';

    #[Validate('required|date')]
    public string $end = '';

    #[Validate('required|integer')]
    public int $team_size = 1;

    public bool $requires_health_certificate = false;

    public bool $requires_clothing_size = false;

    #[Validate('required|integer|min:0')]
    public int $unsubscribe_lock_hours = 24;

    #[Validate('string')]
    public string $category = '';

    public string $searchCategory = '';

    public function mount(Plan $plan, Shift $shift): void
    {
        // authorized by the 'can:update,shift' route middleware
        $this->plan = $plan;
        $this->shift = $shift;
        $this->title = $shift->title;
        $this->description = $shift->description;
        $this->start = Date::parse($shift->start)->format('Y-m-d\TH:i');
        $this->end = Date::parse($shift->end)->format('Y-m-d\TH:i');
        $this->team_size = $shift->team_size;
        $this->requires_health_certificate = (bool) $shift->requires_health_certificate;
        $this->requires_clothing_size = (bool) $shift->requires_clothing_size;
        $this->unsubscribe_lock_hours = $shift->unsubscribe_lock_hours;
        $this->category = (string) $shift->type;
    }

    public function render()
    {
        $shiftCategories = ShiftCategory::where('plan_id', $this->plan->id)->get();

        return view('livewire.shift.edit', [
            'plan' => $this->plan,
            'shift' => $this->shift,
            'shiftCategories' => $shiftCategories,
        ])->title($this->shift->title);
    }

    public function createCategory(): void
    {
        $category = ShiftCategory::create([
            'name' => $this->searchCategory,
            'plan_id' => $this->plan->id,
        ]);
        $this->category = (string) $category->id;
    }

    public function save()
    {
        $this->authorize('update', $this->shift);
        $this->validate();

        $this->shift->update([
            'title' => $this->title,
            'description' => $this->description,
            'start' => $this->start,
            'end' => $this->end,
            'team_size' => $this->team_size,
            'requires_health_certificate' => $this->requires_health_certificate,
            'requires_clothing_size' => $this->requires_clothing_size,
            'unsubscribe_lock_hours' => $this->unsubscribe_lock_hours,
            'type' => $this->category,
        ]);

        Flux::toast(variant: 'success', text: __('shift.successfullyUpdated'));

        return to_route('plan.manage', $this->plan);
    }
}
