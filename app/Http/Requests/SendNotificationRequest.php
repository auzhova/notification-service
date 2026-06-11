<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(NotificationType::class)],
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'message' => 'required|string|max:1000',
            'recipients' => 'required|array|max:1000',
            'recipients.*' => 'string|max:255',
        ];
    }
}
