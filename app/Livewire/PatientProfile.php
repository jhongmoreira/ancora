<?php

namespace App\Livewire;

use App\Models\Patient;
use App\Models\Professional;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PatientProfile extends Component
{
    public Patient $patient;

    public string $full_name = '';

    public ?string $birth_date = null;

    public ?string $gender = null;

    public ?string $contact = null;

    public ?string $notes = null;

    public $professional_id = null;

    public bool $addingProfessional = false;

    public string $new_professional_name = '';

    public ?string $new_professional_contact = null;

    public function mount(): void
    {
        $this->patient = Auth::user()->patient;

        $this->full_name = $this->patient->full_name;
        $this->birth_date = $this->patient->birth_date?->toDateString();
        $this->gender = $this->patient->gender;
        $this->contact = $this->patient->contact;
        $this->notes = $this->patient->notes;
        $this->professional_id = $this->patient->professional_id;
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['nullable', 'in:feminino,masculino,outro,prefiro_nao_informar'],
            'contact' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'professional_id' => ['nullable', 'exists:professionals,id'],
            'new_professional_name' => ['required_if:addingProfessional,true', 'nullable', 'string', 'max:150'],
            'new_professional_contact' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toggleAddProfessional(): void
    {
        $this->addingProfessional = ! $this->addingProfessional;
        $this->new_professional_name = '';
        $this->new_professional_contact = null;
    }

    public function save(): void
    {
        $this->gender = $this->gender ?: null;
        $this->professional_id = $this->professional_id ? (int) $this->professional_id : null;

        $validated = $this->validate();

        if ($this->addingProfessional && filled($this->new_professional_name)) {
            $professional = Professional::create([
                'name' => $this->new_professional_name,
                'contact' => $this->new_professional_contact,
            ]);

            $validated['professional_id'] = $professional->id;
            $this->professional_id = $professional->id;
            $this->addingProfessional = false;
        }

        $this->patient->update([
            'full_name' => $validated['full_name'],
            'birth_date' => $validated['birth_date'],
            'gender' => $validated['gender'],
            'contact' => $validated['contact'],
            'notes' => $validated['notes'],
            'professional_id' => $validated['professional_id'],
        ]);

        session()->flash('status', 'Dados salvos com sucesso.');
    }

    public function render()
    {
        return view('livewire.patient-profile', [
            'professionals' => Professional::orderBy('name')->get(),
        ]);
    }
}
