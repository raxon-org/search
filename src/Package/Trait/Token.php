<?php
namespace Package\Raxon\Search\Trait;

use Error;
use ErrorException;
use Exception;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Module\SharedMemory;
use Raxon\Module\Time;

trait Token {

    /**
     * @throws ObjectException
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function spec(object $flags, object $options): void
    {
        $object = $this->object();
//        $encoded = htmlentities($string, ENT_QUOTES, 'UTF-8');


        $spec = [];
        $spec[] = ' ';
        $spec[] = '  ';
        $spec[] = '   ';
        $spec[] = '    ';
        $spec[] = '        ';
        $spec[] = '<EOF>';
        $spec[] = '<HEADER_START>';
        $spec[] = '<HEADER_END>';
        $spec[] = "\n";
        $spec[] = "\r";
        $spec[] = "\t";
        $spec[] = "\v";
        $spec[] = "\0";
        $spec[] = 'a';
        $spec[] = 'b';
        $spec[] = 'c';
        $spec[] = 'd';
        $spec[] = 'e';
        $spec[] = 'f';
        $spec[] = 'g';
        $spec[] = 'h';
        $spec[] = 'i';
        $spec[] = 'j';
        $spec[] = 'k';
        $spec[] = 'l';
        $spec[] = 'm';
        $spec[] = 'n';
        $spec[] = 'o';
        $spec[] = 'p';
        $spec[] = 'q';
        $spec[] = 'r';
        $spec[] = 's';
        $spec[] = 't';
        $spec[] = 'u';
        $spec[] = 'v';
        $spec[] = 'w';
        $spec[] = 'x';
        $spec[] = 'y';
        $spec[] = 'z';
        $spec[] = 'A';
        $spec[] = 'B';
        $spec[] = 'C';
        $spec[] = 'D';
        $spec[] = 'E';
        $spec[] = 'F';
        $spec[] = 'G';
        $spec[] = 'H';
        $spec[] = 'I';
        $spec[] = 'J';
        $spec[] = 'K';
        $spec[] = 'L';
        $spec[] = 'M';
        $spec[] = 'N';
        $spec[] = 'O';
        $spec[] = 'P';
        $spec[] = 'Q';
        $spec[] = 'R';
        $spec[] = 'S';
        $spec[] = 'T';
        $spec[] = 'U';
        $spec[] = 'V';
        $spec[] = 'W';
        $spec[] = 'X';
        $spec[] = 'Y';
        $spec[] = 'Z';
        $spec[] = '0';
        $spec[] = '1';
        $spec[] = '2';
        $spec[] = '3';
        $spec[] = '4';
        $spec[] = '5';
        $spec[] = '6';
        $spec[] = '7';
        $spec[] = '8';
        $spec[] = '9';
        $spec[] = '`';
        $spec[] = '~';
        $spec[] = '!';
        $spec[] = '@';
        $spec[] = '#';
        $spec[] = '$';
        $spec[] = '%';
        $spec[] = '^';
        $spec[] = '&';
        $spec[] = '*';
        $spec[] = '(';
        $spec[] = ')';
        $spec[] = '-';
        $spec[] = '_';
        $spec[] = '=';
        $spec[] = '+';
        $spec[] = '[';
        $spec[] = ']';
        $spec[] = '{';
        $spec[] = '}';
        $spec[] = ';';
        $spec[] = ':';
        $spec[] = '\'';
        $spec[] = '"';
        $spec[] = '<';
        $spec[] = '>';
        $spec[] = ',';
        $spec[] = '.';
        $spec[] = '/';
        $spec[] = '?';

        $url_spec = $object->config('controller.dir.data') . 'Spec.json';
        $url_output = $object->config('controller.dir.data') . 'Model.json';
        File::delete($url_spec);
        File::delete($url_output);
        File::write($url_spec, Core::object($spec, Core::JSON));
        $dir = new Dir();
        $read = $dir->read('/Application', true);
        $model = [];
        if($read){
            foreach($read as $file){
                if($file->type === File::TYPE){
                    $file->extension = File::extension($file->url);
                    if(
                        in_array(
                            $file->extension,
                            [
                                'php',
                                'html',
                                'css',
                                'js',
                                'tpl',
                                '.md'
                            ], true
                        )
                    ){
                        $file->read = File::read($file->url);
                        $file = $this->transform($file, $spec);
                        if(property_exists($file, 'transform')){
                            foreach ($file->transform as $token) {
                                $model[] = $token;
                            }
                        }
                    }
                }
            }
        }
        File::write($url_output, Core::object($model, Core::JSON));
    }

    /**
     * @throws ObjectException
     */
    public function transform(object $file, array $spec): object
    {
        $object = $this->object();
        $char_to_key = $object->config('char.to.key');
        if($char_to_key === null){
            $char_to_key = [];
            foreach($spec as $nr => $char){
                $char_to_key[$char] = $nr;
            }
            $object->config('char.to.key', $char_to_key);
            $object->config('key.to.char', $spec);
        }
        $header = [];
        $header['url'] = $file->url;
        $header['name'] = $file->name;
        $header['type'] = $file->type;
        $header['mtime'] = File::mtime($file->url);
        $header['size'] = File::size($file->url);
        $header['extension'] = $file->extension;;
        $header = Core::object($header, Core::JSON_LINE);

        $transform = [];
        $transform[] = $char_to_key['<HEADER_START>'] ?? null;
        $split = mb_str_split($header);
        foreach($split as $nr => $char){
            if(array_key_exists($char, $char_to_key)){
                $transform[] = $char_to_key[$char];
            }
        }
        $transform[] = $char_to_key['<HEADER_END>'] ?? null;
        $split = mb_str_split($file->read);
        $skip = 0;
        $block_2 = [];
        $block_3 = [];
        $block_4 = [];
        $block_8 = [];
        $block_16 = [];
        $block_32 = [];
        $block_64 = [];
        foreach($split as $nr => $char){
            if($skip > 0){
                $skip--;
            } else {
                $block_2 = [];
                $block_2[] = $char;
                $block_2[] = $split[$nr + 1] ?? null;
                $block_3 = $block_2;
                $block_3[] = $split[$nr + 2] ?? null;
                $block_4 = $block_3;
                $block_4[] = $split[$nr + 3] ?? null;
                $block_8 = $block_4;
                $block_8[] = $split[$nr + 4] ?? null;
                $block_8[] = $split[$nr + 5] ?? null;
                $block_8[] = $split[$nr + 6] ?? null;
                $block_8[] = $split[$nr + 7] ?? null;
                $block_16 = $block_8;
                $block_16[] = $split[$nr + 8] ?? null;
                $block_16[] = $split[$nr + 9] ?? null;
                $block_16[] = $split[$nr + 10] ?? null;
                $block_16[] = $split[$nr + 11] ?? null;
                $block_16[] = $split[$nr + 12] ?? null;
                $block_16[] = $split[$nr + 13] ?? null;
                $block_16[] = $split[$nr + 14] ?? null;
                $block_16[] = $split[$nr + 15] ?? null;
                $block_32 = $block_16;
                $block_32[] = $split[$nr + 16] ?? null;
                $block_32[] = $split[$nr + 17] ?? null;
                $block_32[] = $split[$nr + 18] ?? null;
                $block_32[] = $split[$nr + 19] ?? null;
                $block_32[] = $split[$nr + 20] ?? null;
                $block_32[] = $split[$nr + 21] ?? null;
                $block_32[] = $split[$nr + 22] ?? null;
                $block_32[] = $split[$nr + 23] ?? null;
                $block_32[] = $split[$nr + 24] ?? null;
                $block_32[] = $split[$nr + 25] ?? null;
                $block_32[] = $split[$nr + 26] ?? null;
                $block_32[] = $split[$nr + 27] ?? null;
                $block_32[] = $split[$nr + 28] ?? null;
                $block_32[] = $split[$nr + 29] ?? null;
                $block_32[] = $split[$nr + 30] ?? null;
                $block_32[] = $split[$nr + 31] ?? null;
                $block_64 = $block_32;
                $block_64[] = $split[$nr + 32] ?? null;
                $block_64[] = $split[$nr + 33] ?? null;
                $block_64[] = $split[$nr + 34] ?? null;
                $block_64[] = $split[$nr + 35] ?? null;
                $block_64[] = $split[$nr + 36] ?? null;
                $block_64[] = $split[$nr + 37] ?? null;
                $block_64[] = $split[$nr + 38] ?? null;
                $block_64[] = $split[$nr + 39] ?? null;
                $block_64[] = $split[$nr + 40] ?? null;
                $block_64[] = $split[$nr + 41] ?? null;
                $block_64[] = $split[$nr + 42] ?? null;
                $block_64[] = $split[$nr + 43] ?? null;
                $block_64[] = $split[$nr + 44] ?? null;
                $block_64[] = $split[$nr + 45] ?? null;
                $block_64[] = $split[$nr + 46] ?? null;
                $block_64[] = $split[$nr + 47] ?? null;
                $block_64[] = $split[$nr + 48] ?? null;
                $block_64[] = $split[$nr + 49] ?? null;
                $block_64[] = $split[$nr + 50] ?? null;
                $block_64[] = $split[$nr + 51] ?? null;
                $block_64[] = $split[$nr + 52] ?? null;
                $block_64[] = $split[$nr + 53] ?? null;
                $block_64[] = $split[$nr + 54] ?? null;
                $block_64[] = $split[$nr + 55] ?? null;
                $block_64[] = $split[$nr + 56] ?? null;
                $block_64[] = $split[$nr + 57] ?? null;
                $block_64[] = $split[$nr + 58] ?? null;
                $block_64[] = $split[$nr + 59] ?? null;
                $block_64[] = $split[$nr + 60] ?? null;
                $block_64[] = $split[$nr + 61] ?? null;
                $block_64[] = $split[$nr + 62] ?? null;
                $block_64[] = $split[$nr + 63] ?? null;
            }
            if(
                $skip === 0 &&
                $block_4[0] === ' ' &&
                $block_4[1] === ' ' &&
                $block_4[2] === ' ' &&
                $block_4[3] === ' '
            ){
                $transform[] = $char_to_key['    '] ?? null;
                $skip = 3;
            }
            elseif(
                $skip === 0 &&
                $block_3[0] === ' ' &&
                $block_3[1] === ' ' &&
                $block_3[2] === ' '
            ){
                $transform[] = $char_to_key['   '] ?? null;
                $skip = 2;
            }
            elseif(
                $skip === 0 &&
                $block_2[0] === ' ' &&
                $block_2[1] === ' '
            ){
                $transform[] = $char_to_key['  '] ?? null;
                $skip = 1;
            }
            elseif(
                $skip === 0 &&
                array_key_exists($char, $char_to_key)
            ){
                $transform[] = $char_to_key[$char];
            }
        }
        $transform[] = $char_to_key['<EOF>'] ?? null;
        $file->transform = $transform;
        return $file;
    }


}


