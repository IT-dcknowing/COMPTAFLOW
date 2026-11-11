<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\AiProviderCredential;

class PythonAiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.python_ai.url', 'https://dc-knowing.com/agent'), '/');
    }

    private function secureTransportAllowed(): bool
    {
        return !app()->environment('production') || str_starts_with($this->baseUrl, 'https://');
    }

    /**
     * Envoie un message de chat à un agent spécifique.
     */
    public function chat(string $message, ?int $clientId = null, string $agentType = 'superviseur', ?string $sessionId = null)
    {
        $sessionId = $sessionId ?? session()->getId();
        $clientId = $clientId ?? (Auth::check() ? Auth::user()->company_id : null);

        if (!$this->secureTransportAllowed()) {
            return ['success' => false, 'error' => "Le service de traitement intelligent rencontre actuellement une difficulté. Merci de contacter le support technique DC-KNOWING.", 'error_type' => 'api_transport_security'];
        }

        $credential = AiProviderCredential::where('is_active', true)->orderByDesc('updated_at')->first();
        if (!$credential) {
            return ['success' => false, 'error' => "Le service de traitement intelligent rencontre actuellement une difficulté. Merci de contacter le support technique DC-KNOWING.", 'error_type' => 'api_not_configured'];
        }

        try {
            $response = Http::timeout(120)->withHeaders([
                'X-AI-Provider' => $credential->provider,
                'X-AI-Model' => $credential->model,
                'X-AI-API-Key' => $credential->api_key,
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/agent/chat", [
                'message' => $message,
                'client_id' => $clientId,
                'session_id' => $sessionId,
                'agent_type' => $agentType,
            ]);

            if ($response->failed()) {
                Log::error("Python AI Chat Error: " . $response->body());
                return ['success' => false, 'error' => "L'IA ne répond pas (Code: " . $response->status() . ")"];
            }

            return ['success' => true, 'data' => $response->json()];

        } catch (\Exception $e) {
            Log::error("Python AI Chat Exception: " . $e->getMessage());
            return ['success' => false, 'error' => "Erreur de connexion à l'IA : " . $e->getMessage()];
        }
    }

    /**
     * Analyse une facture (fichier uploadé).
     */
    public function analyzeInvoice($file, ?string $prompt = null)
    {
        if (!$this->secureTransportAllowed()) {
            return [
                'success' => false,
                'error_type' => 'api_transport_security',
                'error' => "Le service de traitement intelligent rencontre actuellement une difficulté. Merci de contacter le support technique DC-KNOWING.",
                'http_code' => 503,
            ];
        }

        $credential = AiProviderCredential::where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if (!$credential) {
            return [
                'success' => false,
                'error_type' => 'api_not_configured',
                'error' => "Le service de traitement intelligent rencontre actuellement une difficulté. Merci de contacter le support technique DC-KNOWING.",
                'http_code' => 503,
            ];
        }

        try {
            $response = Http::timeout(180)
                ->withHeaders([
                    'X-AI-Provider' => $credential->provider,
                    'X-AI-Model' => $credential->model,
                    'X-AI-API-Key' => $credential->api_key,
                    'Accept' => 'application/json',
                ])
                ->attach(
                    'fichier',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getMimeType()]
                )
                ->post("{$this->baseUrl}/agent/facture", array_filter([
                    'prompt' => $prompt,
                ], fn ($value) => $value !== null && $value !== ''));

            if ($response->failed()) {
                $body = $response->json() ?? [];
                $detail = $body['detail'] ?? [];
                $upstreamCode = is_array($detail) ? ($detail['code'] ?? null) : null;
                $message = is_array($detail) ? ($detail['message'] ?? null) : (is_string($detail) ? $detail : null);
                $status = $response->status();

                $errorType = match ($upstreamCode) {
                    'AI_QUOTA_EXCEEDED' => 'api_quota',
                    'AI_API_KEY_INVALID' => 'api_key',
                    'AI_API_NOT_CONFIGURED' => 'api_not_configured',
                    'AI_PROVIDER_UNSUPPORTED' => 'api_provider',
                    'DOCUMENT_UNREADABLE' => 'document_unreadable',
                    default => $status === 429 ? 'api_quota' : ($status === 422 ? 'document_unreadable' : 'api_unavailable'),
                };

                Log::warning('Python AI invoice request failed', [
                    'provider' => $credential->provider,
                    'model' => $credential->model,
                    'http_status' => $status,
                    'error_type' => $errorType,
                ]);

                return [
                    'success' => false,
                    'error_type' => $errorType,
                    'error' => $errorType === 'api_quota'
                        ? "Le service de traitement intelligent a temporairement atteint sa capacité. Merci de contacter le support technique DC-KNOWING."
                        : "Le service de traitement intelligent rencontre actuellement une difficulté. Merci de contacter le support technique DC-KNOWING.",
                    'http_code' => $status,
                ];
            }

            return ['success' => true, 'data' => $response->json()];

        } catch (\Throwable $e) {
            Log::error("Python AI Facture Exception", [
                'provider' => $credential->provider,
                'model' => $credential->model,
                'exception' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error_type' => 'api_unavailable',
                'error' => "Le service d'analyse est momentanément indisponible. Vérifiez la connexion au serveur IA.",
                'http_code' => 503,
            ];
        }
    }

    public function testCredential(AiProviderCredential $credential): array
    {
        if (!$this->secureTransportAllowed()) {
            return ['success' => false, 'message' => 'Le service de test est temporairement indisponible. Contactez le support technique DC-KNOWING.'];
        }

        try {
            $response = Http::timeout(35)->withHeaders([
                'X-AI-Provider' => $credential->provider,
                'X-AI-Model' => $credential->model,
                'X-AI-API-Key' => $credential->api_key,
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/agent/chat", [
                'message' => 'Réponds uniquement : connexion réussie.',
                'client_id' => null,
                'session_id' => 'superadmin-api-test',
                'agent_type' => 'superviseur',
            ]);

            if ($response->failed()) {
                Log::warning('Super Admin AI credential test failed', [
                    'provider' => $credential->provider,
                    'model' => $credential->model,
                    'http_status' => $response->status(),
                ]);
                return ['success' => false, 'message' => 'La connexion au fournisseur n’a pas abouti. Vérifiez le fournisseur et le modèle, puis contactez le support technique si le problème persiste.'];
            }

            return ['success' => true, 'message' => 'Connexion réussie. Le fournisseur et le modèle répondent correctement.'];
        } catch (\Throwable $e) {
            Log::warning('Super Admin AI credential test exception', [
                'provider' => $credential->provider,
                'model' => $credential->model,
                'exception' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Le service de test est momentanément indisponible. Contactez le support technique DC-KNOWING.'];
        }
    }

    public function calculatePayroll(array $data)
    {
        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/agent/paie", $data);

            if ($response->failed()) {
                Log::error("Python AI Paie Error: " . $response->body());
                return ['success' => false, 'error' => "Échec du calcul (Code: " . $response->status() . ")"];
            }

            return ['success' => true, 'data' => $response->json()];

        } catch (\Exception $e) {
            Log::error("Python AI Paie Exception: " . $e->getMessage());
            return ['success' => false, 'error' => "Erreur lors du calcul de la paie : " . $e->getMessage()];
        }
    }

    /**
     * Vérifie le statut de l'API Python.
     */
    public function getStatus()
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl);
            return $response->json();
        } catch (\Exception $e) {
            return ['statut' => 'indisponible', 'error' => $e->getMessage()];
        }
    }
}
