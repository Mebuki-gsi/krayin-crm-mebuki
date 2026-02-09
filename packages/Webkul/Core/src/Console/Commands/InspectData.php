<?php

namespace Webkul\Core\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Contact\Models\Organization;
use Webkul\Attribute\Models\Attribute;
use Illuminate\Support\Facades\DB;

class InspectData extends Command
{
    protected $signature = 'debug:inspect-data';
    protected $description = 'Inspect CRM Data';

    public function handle()
    {
        $this->info("--- Attributes for Organizations ---");
        $attrs = Attribute::where('entity_type', 'persons')->orWhere('entity_type', 'organizations')->get();
        foreach ($attrs as $attr) {
            $this->info("Code: {$attr->code} | Type: {$attr->type} | Entity: {$attr->entity_type}");
        }

        $this->info("\n--- First 5 Organizations ---");
        $orgs = Organization::take(5)->get();
        foreach ($orgs as $org) {
            $this->info("ID: {$org->id} | Name: {$org->name} | CNPJ Column: " . ($org->cnpj ?? 'NULL'));
            // Try to access custom attributes if they exist dynamically
            if (isset($org->cnpj_custom)) {
                $this->info("CNPJ (Custom?): " . $org->cnpj_custom);
            }
        }
    }
}
