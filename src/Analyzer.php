<?php

namespace CodingAvenue\Proof;

class Analyzer {

    private $file;
    CONST MD_RULES = array('cleancode', 'codesize', 'controversial', 'design', 'naming', 'unusedcode');

    public function __construct(string $file)
    {
        if (!file_exists($file)) {
            throw new \Exception("file $file not found.");
        }

        $this->file = $file;
    }

    public function codingStandard(array $options = array()): array
    {
        $phpcs = VendorBin::getCS();
        $command = sprintf("%s -q --runtime-set ignore_errors_on_exit 1 --runtime-set ignore_warnings_on_exit 1 --report=json --standard=PSR2 %s 2>&1", $phpcs, $this->file);

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 ) {
            throw new \Exception($output[0]);
        }

        $snifferOutput = json_decode($output[0], true);
        $csOutput = [ 'hasViolations' => false ];

        foreach ($snifferOutput['files'] as $file) {
            foreach ($file['messages'] as $message) {
                if (array_key_exists('skipEndTagMessage', $options)
                    && $options['skipEndTagMessage'] && $message['message'] === 'A closing tag is not permitted at the end of a PHP file') {
                        continue;
                }
                
                $csOutput['hasViolations'] = true;

                $csOutput['violations'][] = [
                    'message'   => $message['message'],
                    'line'      => $message['line'],
                    'column'    => $message['column']
                ];
            }
        }

        return $csOutput;   
    }

    public function messDetection(array $rules = array()): array
    {
        foreach ($rules as $rule) {
            if(!in_array($rule, self::MD_RULES)) {
                throw new \Exception("Unknown rule $rule, Available rules are [" . implode(", ", self::MD_RULES) . "]");
            }
        }

        if (empty($rules)) {
            $rules = self::MD_RULES;
        }

        $phpmd = VendorBin::getMD();
        $command = sprintf("%s %s xml %s --ignore-violations-on-exit --suffixes '' 2>&1", $phpmd, $this->file, implode(",", $rules));

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \Exception($output[0]);
        }

        $xml = simplexml_load_string(implode("", $output));

        $mdOutput = [ 'hasViolations' => false ];

        if ($xml->file) {
            foreach($xml->file->children() as $violation) {
                $mdOutput['hasViolations'] = true;

                $mdOutput['violations'][] = [
                    'message'   => trim($violation->__toString()),
                    'beginLine' => $violation['beginline']->__toString(),
                    'endLine'   => $violation['endline']->__toString()
                ];
            }
        }

        return $mdOutput;
    }
}
