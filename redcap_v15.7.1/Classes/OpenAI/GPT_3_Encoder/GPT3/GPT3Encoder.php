<?php
namespace Vanderbilt\REDCap\Classes\OpenAI\GPT_3_Encoder\GPT3;
class GPT3Encoder {
    
    public static $encoder;
    public static $byte_encoder;
    public static $bpe_ranks;
    
    static function gpt_preload() {
        ## If already loaded
        if(!empty(self::$bpe_ranks)) {
            return [];
        }

		// Just return an empty array here since the JSON files below do not exist in the code. Why not?
		return [];
        
        $raw_chars = file_get_contents(dirname(__FILE__) . "/characters.json");
        self::$byte_encoder = json_decode($raw_chars, true);
        if(empty(self::$byte_encoder))
        {
            error_log('Failed to load characters.json: ' . $raw_chars);
            return [];
        }
        $rencoder = file_get_contents(dirname(__FILE__) . "/encoder.json");
        self::$encoder = json_decode($rencoder, true);
        if(empty(self::$encoder))
        {
            error_log('Failed to load encoder.json: ' . self::$rencoder);
            return [];
        }
    
        $bpe_file = file_get_contents(dirname(__FILE__) . "/vocab.bpe");
        if(empty($bpe_file))
        {
            error_log('Failed to load vocab.bpe');
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $bpe_file);
        $bpe_merges = array();
        $bpe_merges_temp = array_slice($lines, 1, count($lines), true);
        foreach($bpe_merges_temp as $bmt)
        {
            $split_bmt = preg_split('#(\s+)#', $bmt);
            $split_bmt = array_filter($split_bmt, [__CLASS__, 'gpt_my_filter']);
            if(count($split_bmt) > 0)
            {
                $bpe_merges[] = $split_bmt;
            }
        }
        self::$bpe_ranks = self::gpt_dictZip($bpe_merges, range(0, count($bpe_merges) - 1));
    }
    
    static function gpt_utf8_encode(string $str): string
    {
        $str .= $str;
        $len = \strlen($str);
        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $str[$i] < "\x80": $str[$j] = $str[$i]; break;
                case $str[$i] < "\xC0": $str[$j] = "\xC2"; $str[++$j] = $str[$i]; break;
                default: $str[$j] = "\xC3"; $str[++$j] = \chr(\ord($str[$i]) - 64); break;
            }
        }
        return substr($str, 0, $j);
    }
    static function gpt_encode($text)
    {
        self::gpt_preload();
        
        $bpe_tokens = [];
        
        preg_match_all("#'s|'t|'re|'ve|'m|'ll|'d| ?\p{L}+| ?\p{N}+| ?[^\s\p{L}\p{N}]+|\s+(?!\S)|\s+#u", $text, $matches);
        if(!isset($matches[0]) || count($matches[0]) == 0)
        {
            error_log('Failed to match string: ' . $text);
            return $bpe_tokens;
        }
        
        $cache = array();
        foreach($matches[0] as $token)
        {
            $new_tokens = array();
            $chars = array();
            $token = self::gpt_utf8_encode($token);
            if(function_exists('mb_strlen'))
            {
                $len = mb_strlen($token, 'UTF-8');
                for ($i = 0; $i < $len; $i++)
                {
                    $chars[] = mb_substr($token, $i, 1, 'UTF-8');
                }
            }
            else
            {
                $chars = str_split($token);
            }
            $result_word = '';
            foreach($chars as $char)
            {
                if(isset(self::$byte_encoder[self::gpt_unichr($char)]))
                {
                    $result_word .= self::$byte_encoder[self::gpt_unichr($char)];
                }
            }
            $new_tokens_bpe = self::gpt_bpe($result_word, self::$bpe_ranks, $cache);
            $new_tokens_bpe = explode(' ', $new_tokens_bpe);
            foreach($new_tokens_bpe as $x)
            {
                if(isset(self::$encoder[$x]))
                {
                    if(isset($new_tokens[$x]))
                    {
                        $new_tokens[] = self::$encoder[$x];
                    }
                    else
                    {
                        $new_tokens[$x] = self::$encoder[$x];
                    }
                }
                else
                {
                    if(isset($new_tokens[$x]))
                    {
                        $new_tokens[] = $x;
                    }
                    else
                    {
                        $new_tokens[$x] = $x;
                    }
                }
            }
            foreach($new_tokens as $ninx => $nval)
            {
                if(isset($bpe_tokens[$ninx]))
                {
                    $bpe_tokens[] = $nval;
                }
                else
                {
                    $bpe_tokens[$ninx] = $nval;
                }
            }
        }
        return $bpe_tokens;
    }
    
    static function gpt_my_filter($var)
    {
        return ($var !== NULL && $var !== FALSE && $var !== '');
    }
    
    static function gpt_unichr($c)
    {
        if (ord($c[0]) >=0 && ord($c[0]) <= 127)
        {
            return ord($c[0]);
        }
        if (ord($c[0]) >= 192 && ord($c[0]) <= 223)
        {
            return (ord($c[0])-192)*64 + (ord($c[1])-128);
        }
        if (ord($c[0]) >= 224 && ord($c[0]) <= 239)
        {
            return (ord($c[0])-224)*4096 + (ord($c[1])-128)*64 + (ord($c[2])-128);
        }
        if (ord($c[0]) >= 240 && ord($c[0]) <= 247)
        {
            return (ord($c[0])-240)*262144 + (ord($c[1])-128)*4096 + (ord($c[2])-128)*64 + (ord($c[3])-128);
        }
        if (ord($c[0]) >= 248 && ord($c[0]) <= 251)
        {
            return (ord($c[0])-248)*16777216 + (ord($c[1])-128)*262144 + (ord($c[2])-128)*4096 + (ord($c[3])-128)*64 + (ord($c[4])-128);
        }
        if (ord($c[0]) >= 252 && ord($c[0]) <= 253)
        {
            return (ord($c[0])-252)*1073741824 + (ord($c[1])-128)*16777216 + (ord($c[2])-128)*262144 + (ord($c[3])-128)*4096 + (ord($c[4])-128)*64 + (ord($c[5])-128);
        }
        if (ord($c[0]) >= 254 && ord($c[0]) <= 255)
        {
            return 0;
        }
        return 0;
    }
    static function gpt_dictZip($x, $y)
    {
        $result = array();
        $cnt = 0;
        foreach($x as $i)
        {
            if(isset($i[1]) && isset($i[0]))
            {
                $result[$i[0] . ',' . $i[1]] = $cnt;
                $cnt++;
            }
        }
        return $result;
    }
    static function gpt_get_pairs($word)
    {
        $pairs = [];
		if (empty($word)) return [];
        $prev_char = $word[0];
        for ($i = 1; $i < count($word); $i++)
        {
            $char = $word[$i];
            $pairs[] = array($prev_char, $char);
            $prev_char = $char;
        }
        return $pairs;
    }
    static function gpt_split($str, $len = 1)
    {
        $arr		= [];
        if(function_exists('mb_strlen'))
        {
            $length 	= mb_strlen($str, 'UTF-8');
        }
        else
        {
            $length 	= strlen($str);
        }
    
        for ($i = 0; $i < $length; $i += $len)
        {
            if(function_exists('mb_substr'))
            {
                $arr[] = mb_substr($str, $i, $len, 'UTF-8');
            }
            else
            {
                $arr[] = substr($str, $i, $len);
            }
        }
        return $arr;
    
    }
    static function gpt_bpe($token, $bpe_ranks, &$cache)
    {
        if(array_key_exists($token, $cache))
        {
            return $cache[$token];
        }
        $word = self::gpt_split($token);
        $init_len = count($word);
        $pairs = self::gpt_get_pairs($word);
        if(!$pairs)
        {
            return $token;
        }
        while (true)
        {
            $minPairs = array();
            foreach($pairs as $pair)
            {
                if(array_key_exists($pair[0] . ','. $pair[1], $bpe_ranks))
                {
                    $rank = $bpe_ranks[$pair[0] . ','. $pair[1]];
                    $minPairs[$rank] = $pair;
                }
                else
                {
                    $minPairs[10e10] = $pair;
                }
            }
            ksort($minPairs);
            $min_key = array_key_first($minPairs);
            foreach($minPairs as $mpi => $mp)
            {
                if($mpi < $min_key)
                {
                    $min_key = $mpi;
                }
            }
            $bigram = $minPairs[$min_key];
            if(!array_key_exists($bigram[0] . ',' . $bigram[1], $bpe_ranks))
            {
                break;
            }
            $first = $bigram[0];
            $second = $bigram[1];
            $new_word = array();
            $i = 0;
            while ($i < count($word))
            {
                $j = self::gpt_indexOf($word, $first, $i);
                if ($j === -1)
                {
                    $new_word = array_merge($new_word, array_slice($word, $i, null, true));
                    break;
                }
                if($i > $j)
                {
                    $slicer = array();
                }
                elseif($j == 0)
                {
                    $slicer = array();
                }
                else
                {
                    $slicer = array_slice($word, $i, $j - $i, true);
                }
                $new_word = array_merge($new_word, $slicer);
                if(count($new_word) > $init_len)
                {
                    break;
                }
                $i = $j;
                if ($word[$i] === $first && $i < count($word) - 1 && $word[$i + 1] === $second)
                {
                    array_push($new_word, $first . $second);
                    $i = $i + 2;
                }
                else
                {
                    array_push($new_word, $word[$i]);
                    $i = $i + 1;
                }
            }
            if($word == $new_word)
            {
                break;
            }
            $word = $new_word;
            if (count($word) === 1)
            {
                break;
            }
            else
            {
                $pairs = self::gpt_get_pairs($word);
            }
        }
        $word = implode(' ', $word);
        $cache[$token] = $word;
        return $word;
    }
    static function gpt_indexOf($arrax, $searchElement, $fromIndex)
    {
        $index = 0;
        foreach($arrax as $index => $value)
        {
            if($index < $fromIndex)
            {
                $index++;
                continue;
            }
            if($value == $searchElement)
            {
                return $index;
            }
            $index++;
        }
        return -1;
    }
}
