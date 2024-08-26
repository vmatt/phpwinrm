<?php
class WinRMClient {
    private $ip;
    private $username;
    private $password;
    private $url;
    private $auth;

    public function __construct($ip, $username, $password) {
        $this->ip = $ip;
        $this->username = $username;
        $this->password = $password;
        $this->url = "http://{$this->ip}:5985/wsman";
        $this->auth = base64_encode("{$this->username}:{$this->password}");
    }

    private function send_winrm_request($headers, $body) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 2000);


        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }

    private function get_headers() {
        return [
            "Host: {$this->ip}:5985",
            "User-Agent: PHP WinRM client",
            "Accept-Encoding: gzip, deflate, br",
            "Accept: */*",
            "Connection: keep-alive",
            "Content-Type: application/soap+xml;charset=UTF-8",
            "Authorization: Basic {$this->auth}"
        ];
    }

    public function execute_command($command) {
        $headers = $this->get_headers();

        // Step 1: Create Shell
        $create_shell_body = $this->get_create_shell_body();
        $response = $this->send_winrm_request($headers, $create_shell_body);
        $shell_id = preg_match('/<rsp:ShellId>(.*?)<\/rsp:ShellId>/s', $response, $matches) ? $matches[1] : null;

        if (!$shell_id) {
            return "Failed to create shell session.";
        }

        // Step 2: Execute Command
        $execute_command_body = $this->get_execute_command_body($shell_id, $command);
        $response = $this->send_winrm_request($headers, $execute_command_body);
        $command_id = preg_match('/<rsp:CommandId>(.*?)<\/rsp:CommandId>/s', $response, $matches) ? $matches[1] : null;

        if (!$command_id) {
            return "Failed to execute command.";
        }

        // Step 3: Receive Command Output
        $receive_output_body = $this->get_receive_output_body($shell_id, $command_id);
        $response = $this->send_winrm_request($headers, $receive_output_body);
        $output = $this->parse_command_output($response, $command_id);

        // Step 4: Signal Termination
        $signal_termination_body = $this->get_signal_termination_body($shell_id, $command_id);
        $this->send_winrm_request($headers, $signal_termination_body);

        // Step 5: Delete Shell Session
        $delete_shell_body = $this->get_delete_shell_body($shell_id);
        $this->send_winrm_request($headers, $delete_shell_body);

        return $output;
    }

    private function get_create_shell_body() {
        // XML body for creating a shell
        return '<?xml version="1.0" encoding="utf-8"?>
        <env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:env="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:b="http://schemas.dmtf.org/wbem/wsman/1/cimbinding.xsd" xmlns:n="http://schemas.xmlsoap.org/ws/2004/09/enumeration" xmlns:x="http://schemas.xmlsoap.org/ws/2004/09/transfer" xmlns:w="http://schemas.dmtf.org/wbem/wsman/1/wsman.xsd" xmlns:p="http://schemas.microsoft.com/wbem/wsman/1/wsman.xsd" xmlns:rsp="http://schemas.microsoft.com/wbem/wsman/1/windows/shell" xmlns:cfg="http://schemas.microsoft.com/wbem/wsman/1/config">
            <env:Header>
                <a:To>http://' . $this->ip . ':5985/wsman</a:To>
                <a:ReplyTo>
                    <a:Address mustUnderstand="true">http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</a:Address>
                </a:ReplyTo>
                <w:MaxEnvelopeSize mustUnderstand="true">153600</w:MaxEnvelopeSize>
                <a:MessageID>uuid:' . uniqid() . '</a:MessageID>
                <w:Locale mustUnderstand="false" xml:lang="en-US"/>
                <p:DataLocale mustUnderstand="false" xml:lang="en-US"/>
                <w:OperationTimeout>PT60S</w:OperationTimeout>
                <w:ResourceURI mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd</w:ResourceURI>
                <a:Action mustUnderstand="true">http://schemas.xmlsoap.org/ws/2004/09/transfer/Create</a:Action>
                <w:OptionSet>
                    <w:Option Name="WINRS_NOPROFILE">FALSE</w:Option>
                    <w:Option Name="WINRS_CODEPAGE">437</w:Option>
                </w:OptionSet>
            </env:Header>
            <env:Body>
                <rsp:Shell>
                    <rsp:InputStreams>stdin</rsp:InputStreams>
                    <rsp:OutputStreams>stdout stderr</rsp:OutputStreams>
                </rsp:Shell>
            </env:Body>
        </env:Envelope>';
    }

    private function get_execute_command_body($shell_id, $command) {
        // XML body for executing a command
        return '<?xml version="1.0" encoding="utf-8"?>
        <env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:env="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:b="http://schemas.dmtf.org/wbem/wsman/1/cimbinding.xsd" xmlns:n="http://schemas.xmlsoap.org/ws/2004/09/enumeration" xmlns:x="http://schemas.xmlsoap.org/ws/2004/09/transfer" xmlns:w="http://schemas.dmtf.org/wbem/wsman/1/wsman.xsd" xmlns:p="http://schemas.microsoft.com/wbem/wsman/1/wsman.xsd" xmlns:rsp="http://schemas.microsoft.com/wbem/wsman/1/windows/shell" xmlns:cfg="http://schemas.microsoft.com/wbem/wsman/1/config">
            <env:Header>
                <a:To>http://' . $this->ip . ':5985/wsman</a:To>
                <a:ReplyTo>
                    <a:Address mustUnderstand="true">http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</a:Address>
                </a:ReplyTo>
                <w:MaxEnvelopeSize mustUnderstand="true">153600</w:MaxEnvelopeSize>
                <a:MessageID>uuid:' . uniqid() . '</a:MessageID>
                <w:Locale mustUnderstand="false" xml:lang="en-US"/>
                <p:DataLocale mustUnderstand="false" xml:lang="en-US"/>
                <w:OperationTimeout>PT60S</w:OperationTimeout>
                <w:ResourceURI mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd</w:ResourceURI>
                <a:Action mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/Command</a:Action>
                <w:SelectorSet>
                    <w:Selector Name="ShellId">' . $shell_id . '</w:Selector>
                </w:SelectorSet>
            </env:Header>
            <env:Body>
                <rsp:CommandLine>
                    <rsp:Command>' . $command . '</rsp:Command>
                </rsp:CommandLine>
            </env:Body>
        </env:Envelope>';
    }

    private function get_receive_output_body($shell_id, $command_id) {
        // XML body for receiving command output
        return '<?xml version="1.0" encoding="utf-8"?>
        <env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:env="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:b="http://schemas.dmtf.org/wbem/wsman/1/cimbinding.xsd" xmlns:n="http://schemas.xmlsoap.org/ws/2004/09/enumeration" xmlns:x="http://schemas.xmlsoap.org/ws/2004/09/transfer" xmlns:w="http://schemas.dmtf.org/wbem/wsman/1/wsman.xsd" xmlns:p="http://schemas.microsoft.com/wbem/wsman/1/wsman.xsd" xmlns:rsp="http://schemas.microsoft.com/wbem/wsman/1/windows/shell" xmlns:cfg="http://schemas.microsoft.com/wbem/wsman/1/config">
            <env:Header>
                <a:To>http://' . $this->ip . ':5985/wsman</a:To>
                <a:ReplyTo>
                    <a:Address mustUnderstand="true">http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</a:Address>
                </a:ReplyTo>
                <w:MaxEnvelopeSize mustUnderstand="true">153600</w:MaxEnvelopeSize>
                <a:MessageID>uuid:' . uniqid() . '</a:MessageID>
                <w:Locale mustUnderstand="false" xml:lang="en-US"/>
                <p:DataLocale mustUnderstand="false" xml:lang="en-US"/>
                <w:OperationTimeout>PT60S</w:OperationTimeout>
                <w:ResourceURI mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd</w:ResourceURI>
                <a:Action mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/Receive</a:Action>
                <w:SelectorSet>
                    <w:Selector Name="ShellId">' . $shell_id . '</w:Selector>
                </w:SelectorSet>
            </env:Header>
            <env:Body>
                <rsp:Receive>
                    <rsp:DesiredStream CommandId="' . $command_id . '">stdout stderr</rsp:DesiredStream>
                </rsp:Receive>
            </env:Body>
        </env:Envelope>';
    }

    private function parse_command_output($response, $command_id) {
        preg_match_all('/<rsp:Stream Name="stdout" CommandId="' . $command_id . '">(.*?)<\/rsp:Stream>/s', $response, $matches);
        $output = '';
        foreach ($matches[1] as $base64_output) {
            $decoded = base64_decode($base64_output);
            // Normalize line endings to \n
            $decoded = preg_replace('/\r\n|\r/', "\n", $decoded);
            $output .= $decoded;
        }
        return trim($output); // Remove any leading/trailing whitespace
    }

    private function get_signal_termination_body($shell_id, $command_id) {
        // XML body for signaling termination
        return '<?xml version="1.0" encoding="utf-8"?>
        <env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:env="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:b="http://schemas.dmtf.org/wbem/wsman/1/cimbinding.xsd" xmlns:n="http://schemas.xmlsoap.org/ws/2004/09/enumeration" xmlns:x="http://schemas.xmlsoap.org/ws/2004/09/transfer" xmlns:w="http://schemas.dmtf.org/wbem/wsman/1/wsman.xsd" xmlns:p="http://schemas.microsoft.com/wbem/wsman/1/wsman.xsd" xmlns:rsp="http://schemas.microsoft.com/wbem/wsman/1/windows/shell" xmlns:cfg="http://schemas.microsoft.com/wbem/wsman/1/config">
            <env:Header>
                <a:To>http://' . $this->ip . ':5985/wsman</a:To>
                <a:ReplyTo>
                    <a:Address mustUnderstand="true">http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</a:Address>
                </a:ReplyTo>
                <w:MaxEnvelopeSize mustUnderstand="true">153600</w:MaxEnvelopeSize>
                <a:MessageID>uuid:' . uniqid() . '</a:MessageID>
                <w:Locale mustUnderstand="false" xml:lang="en-US"/>
                <p:DataLocale mustUnderstand="false" xml:lang="en-US"/>
                <w:OperationTimeout>PT60S</w:OperationTimeout>
                <w:ResourceURI mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd</w:ResourceURI>
                <a:Action mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/Signal</a:Action>
                <w:SelectorSet>
                    <w:Selector Name="ShellId">' . $shell_id . '</w:Selector>
                </w:SelectorSet>
            </env:Header>
            <env:Body>
                <rsp:Signal CommandId="' . $command_id . '">
                    <rsp:Code>http://schemas.microsoft.com/wbem/wsman/1/windows/shell/signal/terminate</rsp:Code>
                </rsp:Signal>
            </env:Body>
        </env:Envelope>';
    }

    private function get_delete_shell_body($shell_id) {
        // XML body for deleting the shell session
        return '<?xml version="1.0" encoding="utf-8"?>
        <env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:env="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:b="http://schemas.dmtf.org/wbem/wsman/1/cimbinding.xsd" xmlns:n="http://schemas.xmlsoap.org/ws/2004/09/enumeration" xmlns:x="http://schemas.xmlsoap.org/ws/2004/09/transfer" xmlns:w="http://schemas.dmtf.org/wbem/wsman/1/wsman.xsd" xmlns:p="http://schemas.microsoft.com/wbem/wsman/1/wsman.xsd" xmlns:rsp="http://schemas.microsoft.com/wbem/wsman/1/windows/shell" xmlns:cfg="http://schemas.microsoft.com/wbem/wsman/1/config">
            <env:Header>
                <a:To>http://' . $this->ip . ':5985/wsman</a:To>
                <a:ReplyTo>
                    <a:Address mustUnderstand="true">http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</a:Address>
                </a:ReplyTo>
                <w:MaxEnvelopeSize mustUnderstand="true">153600</w:MaxEnvelopeSize>
                <a:MessageID>uuid:' . uniqid() . '</a:MessageID>
                <w:Locale mustUnderstand="false" xml:lang="en-US"/>
                <p:DataLocale mustUnderstand="false" xml:lang="en-US"/>
                <w:OperationTimeout>PT60S</w:OperationTimeout>
                <w:ResourceURI mustUnderstand="true">http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd</w:ResourceURI>
                <a:Action mustUnderstand="true">http://schemas.xmlsoap.org/ws/2004/09/transfer/Delete</a:Action>
                <w:SelectorSet>
                    <w:Selector Name="ShellId">' . $shell_id . '</w:Selector>
                </w:SelectorSet>
            </env:Header>
            <env:Body></env:Body>
        </env:Envelope>';
    }
}

// Example usage:
// $client = new WinRMClient('192.168.1.100', 'username', 'password');
// $output = $client->execute_command('dir C:\\');
// echo $output;
