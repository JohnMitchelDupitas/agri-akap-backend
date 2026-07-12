<?php

/*
|--------------------------------------------------------------------------
| Municipal Pest & Disease Intervention Guidelines
|--------------------------------------------------------------------------
| Standardized regional threat labels used by the field logging form, plus
| the MAO-approved prescriptive countermeasure for each. Editable by the
| office without touching controller code. Resolution is case-insensitive;
| unknown pests fall back to `default`. High/Critical reports append the
| `escalation` directive.
*/

return [

    // Standardized threat labels surfaced in FieldIntelligencePage.vue.
    'threats' => [
        'Fall Armyworm',
        'Rice Blast',
        'Brown Planthopper',
        'Rice Black Bug',
        'Tungro Virus',
        'Golden Apple Snail',
        'Corn Borer',
        'Rice Bug',
        'Rodents',
    ],

    // Pre-approved countermeasure per threat.
    'interventions' => [
        'Fall Armyworm' => 'Deploy Metarhizium anisopliae bio-control; hand-pick egg masses; spray an approved insecticide only once damage passes the action threshold.',
        'Rice Blast' => 'Reduce nitrogen fertilizer application; maintain proper field drainage; apply Tricyclazole fungicide to affected patches.',
        'Brown Planthopper' => 'Drain the field for 3-4 days; avoid broad-spectrum insecticides that kill natural predators; plant resistant varieties next cycle.',
        'Rice Black Bug' => 'Practice synchronous planting and field sanitation; set up light traps at night; apply recommended insecticide if populations surge.',
        'Tungro Virus' => 'Rogue and destroy infected hills immediately; control the green leafhopper vector; adopt tungro-resistant varieties.',
        'Golden Apple Snail' => 'Handpick snails and egg masses; install screens at water inlets; use recommended molluscicide or attractant baiting.',
        'Corn Borer' => 'Detassel and destroy infested plant residues; release Trichogramma egg parasitoids; apply granular insecticide into leaf whorls.',
        'Rice Bug' => 'Cut and remove surrounding weeds; conduct synchronous harvesting; net-sweep during heading stage and spray only if threshold exceeded.',
        'Rodents' => 'Coordinate a community-wide trapping (community trap-barrier system); maintain clean bunds; use approved rodenticide baiting stations.',
    ],

    // Used when the reported pest is not in the map above.
    'default' => 'Isolate the affected area and coordinate with the assigned MAO technician for a site-specific countermeasure.',

    // Appended for High / Critical severity reports.
    'escalation' => 'URGENT: Initiate localized chemical spraying and notify the MAO office immediately to contain the spread.',

];
