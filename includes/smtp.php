<?php
class SimpleSMTP
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    private $debug = false;

    public function __construct($host, $port, $username, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function setDebug($debug = true)
    {
        $this->debug = $debug;
    }

    public function send($from, $fromName, $to, $subject, $body, $isHTML = true)
    {
        try {
            if (!$this->connect()) {
                return false;
            }

            $result = $this->sendMessage($from, $fromName, $to, $subject, $body, $isHTML);

            $this->disconnect();

            return $result;
        } catch (Exception $e) {
            $this->logError("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    private function connect()
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        if ($this->port == 465) {
            $connectionString = "ssl://{$this->host}:{$this->port}";
        } else {
            $connectionString = "tcp://{$this->host}:{$this->port}";
        }

        $this->socket = @stream_socket_client(
            $connectionString,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            $this->logError("Connection failed: $errstr ($errno)");
            return false;
        }

        $response = $this->readResponse();
        if (!$this->checkResponse($response, 220)) {
            return false;
        }

        $this->sendCommand("EHLO " . $this->host);
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 250)) {
            return false;
        }

        if ($this->port == 587) {
            $this->sendCommand("STARTTLS");
            $response = $this->readResponse();
            if (!$this->checkResponse($response, 220)) {
                return false;
            }

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->logError("Failed to enable TLS encryption");
                return false;
            }

            $this->sendCommand("EHLO " . $this->host);
            $response = $this->readResponse();
            if (!$this->checkResponse($response, 250)) {
                return false;
            }
        }

        $this->sendCommand("AUTH LOGIN");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 334)) {
            return false;
        }

        $this->sendCommand(base64_encode($this->username));
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 334)) {
            return false;
        }

        $this->sendCommand(base64_encode($this->password));
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 235)) {
            return false;
        }

        return true;
    }

    private function sendMessage($from, $fromName, $to, $subject, $body, $isHTML)
    {
        $this->sendCommand("MAIL FROM: <{$from}>");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 250)) {
            return false;
        }

        $this->sendCommand("RCPT TO: <{$to}>");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 250)) {
            return false;
        }

        $this->sendCommand("DATA");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 354)) {
            return false;
        }

        $message = $this->buildMessage($from, $fromName, $to, $subject, $body, $isHTML);
        $this->sendCommand($message . "\r\n.");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, 250)) {
            return false;
        }

        return true;
    }

    private function buildMessage($from, $fromName, $to, $subject, $body, $isHTML)
    {
        $headers = [];
        $headers[] = "From: {$fromName} <{$from}>";
        $headers[] = "To: {$to}";
        $headers[] = "Subject: {$subject}";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . md5(uniqid()) . "@{$this->host}>";
        $headers[] = "MIME-Version: 1.0";

        if ($isHTML) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        $headers[] = "Content-Transfer-Encoding: 8bit";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function sendCommand($command)
    {
        if ($this->debug) {
            $this->logError("SEND: " . $command);
        }
        fwrite($this->socket, $command . "\r\n");
    }

    private function readResponse()
    {
        $response = '';
        while (($line = fgets($this->socket, 512)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }

        if ($this->debug) {
            $this->logError("RECV: " . trim($response));
        }

        return $response;
    }

    private function checkResponse($response, $expectedCode)
    {
        $code = intval(substr($response, 0, 3));
        return $code == $expectedCode;
    }

    private function disconnect()
    {
        if ($this->socket) {
            $this->sendCommand("QUIT");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function logError($message)
    {
        error_log("SimpleSMTP: " . $message);

        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
        file_put_contents($logDir . '/smtp_debug.log', $logMessage, FILE_APPEND | LOCK_EX);
    }
}
