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

        $dir = new Dir();
        $read = $dir->read('/Application', true);
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
                        ddd($file);
                    }
                }
            }
        }


        ddd($spec);
    }


}


