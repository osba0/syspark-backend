<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FournisseurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nom'         => $this->nom,
            'type'        => $this->type,
            'telephone'   => $this->telephone,
            'email'       => $this->email,
            'adresse'     => $this->adresse,
            'ville'       => $this->ville,
            'specialite'  => $this->specialite,
            'ninea'       => $this->ninea,
            'est_actif'   => $this->est_actif,
            'notes'       => $this->notes,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
