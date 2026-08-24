<?php

namespace App\Livewire\Shift;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\ShiftCategory;
use Flux\Flux;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Create extends Component
{
    #[Locked]
    public Plan $plan;

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

    public function mount(Plan $plan): void
    {
        // authorized by the 'can:create,App\Models\Shift,plan' route middleware
        $this->plan = $plan;
    }

    public function updatedStart(): void
    {
        if ($this->start === '' || $this->end !== '') {
            return;
        }

        $this->end = Date::parse($this->start)->addHour()->format('Y-m-d\TH:i');
    }

    public function render()
    {
        $groups = Shift::select('group')
            ->distinct()
            ->whereBelongsTo($this->plan)
            ->get()
            ->count();
        $shift = new Shift;
        $shiftCategories = ShiftCategory::where('plan_id', $this->plan->id)->get();

        return view('livewire.shift.create', [
            'plan' => $this->plan,
            'shift' => $shift,
            'groups' => $groups,
            'shiftCategories' => $shiftCategories,
        ])->title(__('shift.createHeading'));
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
        $this->authorize('create', [Shift::class, $this->plan]);
        $this->validate();

        $this->plan->shifts()->create([
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

        Flux::toast(variant: 'success', text: __('shift.successfullyCreated'));

        return to_route('plan.manage', $this->plan);
    }
}
