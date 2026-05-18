<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channel' => 'required|in:sms,email',
            'message' => 'required|string|max:1000',
            'recipient_ids' => 'required|array|min:1|max:1000',
            'recipient_ids.*' => 'string|max:255',
            'priority' => 'sometimes|in:high,low',
            'idempotency_key' => 'sometimes|string|max:255'
        ];
    }
}
