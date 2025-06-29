<?php

namespace MuhammadSadeeq\ActivitylogUi\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExportCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = config('activitylog-ui.exports.notifications.mail.subject', 'Your Activity Log Export is Ready');
        $fromAddress = config('activitylog-ui.exports.notifications.mail.from_address', config('mail.from.address'));
        $fromName = config('activitylog-ui.exports.notifications.mail.from_name', 'Activity Log Exports');

        return $this->from($fromAddress, $fromName)
                    ->subject($subject)
                    ->view('activitylog-ui::mail.export-completed')
                    ->with($this->data);
    }
}
