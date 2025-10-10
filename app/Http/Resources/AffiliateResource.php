<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'affiliate_code' => $this->affiliate_code,
            // 'affiliate_link' => url("/api/affiliate/click/{$this->affiliate_code}"),
            'affiliate_link' => url("/api/affiliate/click/{$this->affiliate_code}"),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
