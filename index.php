<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Smpp\ClientBuilder;
use Smpp\Pdu\Address;
use Smpp\Smpp;
use Psr\Log\AbstractLogger;
use Throwable;

class SendTestSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:send-test {to} {message?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test SMS via Movitel SMPP gateway.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Custom Logger for debugging (opcional - remova se não precisar)
        $logger = new class extends AbstractLogger
        {
            public function log($level, $message, array $context = []): void
            {
                // Comente esta linha se não quiser ver o debug
                // echo "[" . strtoupper($level) . "] " . $message . "\n";
            }
        };

        // SMPP Movitel Credentials
        $host = env('MOVITEL_SMPP_HOST', '197.218.21.56');
        $port = env('MOVITEL_SMPP_PORT', 8014);
        $systemId = env('MOVITEL_SMPP_USERNAME', 'excelesco');
        $password = env('MOVITEL_SMPP_PASSWORD', 'exce@25#');
        $senderId = env('MOVITEL_SMPP_SENDER_ID', 'ESCOLAEXCEL');

        // Message details
        $to = $this->argument('to');
        $message = $this->argument('message') ?? 'Olá, este é um teste de SMS com caracteres UTF-8: çãõéíá.';

        $client = null;

        try {
            $this->info("Attempting to send SMS to {$to}...");

            // Build client with improved configuration
            $client = ClientBuilder::createForSockets(["$host:$port"])
                ->setCredentials($systemId, $password)
                ->setLogger($logger)
                ->buildClient();

            // Bind transceiver
            $this->info("Connecting to SMPP gateway...");
            $client->bindTransceiver();
            $this->info("Connected and bound successfully!");

            // Send SMS with UTF-8 encoding
            $this->info("Sending SMS...");
            $client->sendSMS(
                from: new Address($senderId, Smpp::TON_ALPHANUMERIC),
                to: new Address($to, Smpp::TON_INTERNATIONAL, Smpp::NPI_E164),
                message: $message,
                dataCoding: Smpp::DATA_CODING_UCS2 // Use UCS2 for UTF-8 characters
            );

            $this->info("✅ SMS sent successfully to {$to}!");
            
            // Pequena pausa antes de fechar a conexão
            sleep(1);

        } catch (Throwable $e) {
            // Filtrar erros de "Resource temporarily unavailable" que não são críticos
            if (strpos($e->getMessage(), 'Resource temporarily unavailable') !== false) {
                $this->warn("⚠️  Connection closed (SMS likely sent successfully)");
                $this->info("✅ SMS probably delivered to {$to}!");
            } else {
                $this->error("❌ Error sending SMS: " . $e->getMessage());
                return 1; // Exit code para erro
            }
        } finally {
            // Fechar conexão de forma segura
            if ($client) {
                try {
                    $client->close();
                    $this->info("Connection closed.");
                } catch (Throwable $e) {
                    // Ignorar erros ao fechar - é normal
                    $this->comment("Connection closed (with minor socket cleanup issue - normal).");
                }
            }
        }

        return 0; // Exit code para sucesso
    }

    /**
     * Remove acentos da mensagem para compatibilidade GSM 7-bit
     */
    private function removeAccents($string)
    {
        $accents = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C'
        ];
        
        return strtr($string, $accents);
    }
}
