<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageContactRequest;
use App\Mail\AccuseReceptionContactMail;
use App\Mail\NouveauMessageContactMail;
use App\Models\MessageContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactController extends Controller
{
    /**
     * Formulaire de contact du site vitrine — route publique (throttle:5,1 sur
     * la route pour limiter le spam). Le message est toujours enregistré même
     * si l'envoi des e-mails échoue (ex : SMTP momentanément indisponible).
     */
    public function store(StoreMessageContactRequest $request): JsonResponse
    {
        $messageContact = MessageContact::create($request->validated());

        try {
            Mail::to(config('app.contact_admin_email'))->send(new NouveauMessageContactMail($messageContact));
            Mail::to($messageContact->email)->send(new AccuseReceptionContactMail($messageContact));
        } catch (Throwable $e) {
            Log::warning("Échec de l'envoi d'un e-mail lié au formulaire de contact.", [
                'message_contact_id' => $messageContact->id,
                'erreur' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'data' => $messageContact,
            'message' => 'Votre message a bien été envoyé. Nous vous répondrons rapidement.',
        ], 201);
    }
}
