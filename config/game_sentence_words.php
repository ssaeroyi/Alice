<?php

/*
|--------------------------------------------------------------------------
| Sentence Builder whitelist
|--------------------------------------------------------------------------
|
| Curated set of clean, student-friendly words used by the Sentence Builder
| game. The offline dictionary POS tagging is unreliable (e.g. "wolves"
| tagged as a verb, "castle" as a verb, "Borgo" pos=null, tokens keeping
| quotes/dashes/punctuation), so `pickSentences()` only blanks words found
| here with a known part of speech. This guarantees every blank displays
| clean text and maps to a sane word type.
|
| Format: 'word' => 'pos'  where pos is one of: noun, verb, adj, adv, function
|
*/

return [

    // --- nouns ---
    'apple' => 'noun', 'animal' => 'noun', 'arm' => 'noun', 'baby' => 'noun',
    'bag' => 'noun', 'ball' => 'noun', 'beach' => 'noun', 'bed' => 'noun',
    'bird' => 'noun', 'boat' => 'noun', 'book' => 'noun', 'box' => 'noun',
    'boy' => 'noun', 'bridge' => 'noun', 'brother' => 'noun', 'bus' => 'noun',
    'cake' => 'noun', 'car' => 'noun', 'cat' => 'noun', 'chair' => 'noun',
    'child' => 'noun', 'children' => 'noun', 'city' => 'noun', 'class' => 'noun',
    'clock' => 'noun', 'cloud' => 'noun', 'coat' => 'noun', 'country' => 'noun',
    'cup' => 'noun', 'dog' => 'noun', 'door' => 'noun', 'dress' => 'noun',
    'duck' => 'noun', 'ear' => 'noun', 'egg' => 'noun', 'eye' => 'noun',
    'face' => 'noun', 'family' => 'noun', 'farm' => 'noun', 'father' => 'noun',
    'field' => 'noun', 'fire' => 'noun', 'fish' => 'noun', 'flower' => 'noun',
    'food' => 'noun', 'foot' => 'noun', 'forest' => 'noun', 'friend' => 'noun',
    'fruit' => 'noun', 'game' => 'noun', 'garden' => 'noun', 'girl' => 'noun',
    'glass' => 'noun', 'grandmother' => 'noun', 'grandfather' => 'noun',
    'grass' => 'noun', 'ground' => 'noun', 'hair' => 'noun', 'hand' => 'noun',
    'hat' => 'noun', 'head' => 'noun', 'heart' => 'noun', 'hill' => 'noun',
    'home' => 'noun', 'horse' => 'noun', 'house' => 'noun', 'ice' => 'noun',
    'island' => 'noun', 'jacket' => 'noun', 'king' => 'noun', 'kitchen' => 'noun',
    'lake' => 'noun', 'lamp' => 'noun', 'leg' => 'noun', 'letter' => 'noun',
    'library' => 'noun', 'light' => 'noun', 'lion' => 'noun', 'milk' => 'noun',
    'money' => 'noun', 'month' => 'noun', 'moon' => 'noun', 'morning' => 'noun',
    'mother' => 'noun', 'mountain' => 'noun', 'mouth' => 'noun', 'music' => 'noun',
    'name' => 'noun', 'night' => 'noun', 'nose' => 'noun', 'ocean' => 'noun',
    'office' => 'noun', 'orange' => 'noun', 'parent' => 'noun', 'park' => 'noun',
    'party' => 'noun', 'pen' => 'noun', 'pencil' => 'noun', 'people' => 'noun',
    'phone' => 'noun', 'picture' => 'noun', 'pig' => 'noun', 'place' => 'noun',
    'plate' => 'noun', 'play' => 'noun', 'pool' => 'noun', 'queen' => 'noun',
    'rain' => 'noun', 'river' => 'noun', 'road' => 'noun', 'rock' => 'noun',
    'roof' => 'noun', 'room' => 'noun', 'school' => 'noun', 'sea' => 'noun',
    'seed' => 'noun', 'sheep' => 'noun', 'ship' => 'noun', 'shoe' => 'noun',
    'shop' => 'noun', 'sister' => 'noun', 'sky' => 'noun', 'snake' => 'noun',
    'snow' => 'noun', 'song' => 'noun', 'soup' => 'noun', 'sound' => 'noun',
    'star' => 'noun', 'station' => 'noun', 'stone' => 'noun', 'store' => 'noun',
    'storm' => 'noun', 'story' => 'noun', 'street' => 'noun', 'student' => 'noun',
    'sun' => 'noun', 'table' => 'noun', 'teacher' => 'noun', 'team' => 'noun',
    'tiger' => 'noun', 'time' => 'noun', 'town' => 'noun', 'train' => 'noun',
    'tree' => 'noun', 'village' => 'noun', 'voice' => 'noun', 'wall' => 'noun',
    'water' => 'noun', 'weather' => 'noun', 'week' => 'noun', 'window' => 'noun',
    'wind' => 'noun', 'woman' => 'noun', 'word' => 'noun',
    'world' => 'noun', 'year' => 'noun',

    // --- verbs ---
    'answer' => 'verb', 'ask' => 'verb', 'began' => 'verb', 'begin' => 'verb',
    'believe' => 'verb', 'bring' => 'verb', 'brings' => 'verb', 'build' => 'verb',
    'buy' => 'verb', 'call' => 'verb', 'came' => 'verb', 'carry' => 'verb',
    'catch' => 'verb', 'climb' => 'verb', 'come' => 'verb', 'cook' => 'verb',
    'cry' => 'verb', 'cut' => 'verb', 'dance' => 'verb', 'draw' => 'verb',
    'drink' => 'verb', 'drive' => 'verb', 'eat' => 'verb', 'fall' => 'verb',
    'feel' => 'verb', 'find' => 'verb', 'finished' => 'verb', 'fix' => 'verb',
    'fly' => 'verb', 'follow' => 'verb', 'forget' => 'verb', 'found' => 'verb',
    'gave' => 'verb', 'get' => 'verb', 'give' => 'verb', 'gives' => 'verb',
    'grow' => 'verb', 'guess' => 'verb', 'help' => 'verb', 'hide' => 'verb',
    'hold' => 'verb', 'hope' => 'verb', 'jump' => 'verb', 'keep' => 'verb',
    'kick' => 'verb', 'know' => 'verb', 'laugh' => 'verb', 'learn' => 'verb',
    'leave' => 'verb', 'let' => 'verb', 'like' => 'verb', 'listen' => 'verb',
    'live' => 'verb', 'look' => 'verb', 'lose' => 'verb', 'love' => 'verb',
    'make' => 'verb', 'made' => 'verb', 'meet' => 'verb', 'move' => 'verb',
    'need' => 'verb', 'open' => 'verb', 'paint' => 'verb', 'park' => 'verb',
    'play' => 'verb', 'point' => 'verb', 'pull' => 'verb', 'push' => 'verb',
    'put' => 'verb', 'read' => 'verb', 'remember' => 'verb', 'ride' => 'verb',
    'run' => 'verb', 'said' => 'verb', 'say' => 'verb', 'see' => 'verb',
    'sell' => 'verb', 'send' => 'verb', 'show' => 'verb', 'sing' => 'verb',
    'sit' => 'verb', 'sleep' => 'verb', 'smile' => 'verb', 'speak' => 'verb',
    'spend' => 'verb', 'stand' => 'verb', 'start' => 'verb', 'stay' => 'verb',
    'stop' => 'verb', 'study' => 'verb', 'swim' => 'verb', 'take' => 'verb',
    'talk' => 'verb', 'teach' => 'verb', 'tell' => 'verb', 'think' => 'verb',
    'throw' => 'verb', 'touch' => 'verb', 'travel' => 'verb', 'try' => 'verb',
    'turn' => 'verb', 'understand' => 'verb', 'use' => 'verb', 'visit' => 'verb',
    'wait' => 'verb', 'walk' => 'verb', 'want' => 'verb', 'wash' => 'verb',
    'watch' => 'verb', 'wear' => 'verb', 'win' => 'verb', 'wish' => 'verb',
    'work' => 'verb', 'write' => 'verb',

    // --- adjectives ---
    'angry' => 'adj', 'bad' => 'adj', 'beautiful' => 'adj', 'big' => 'adj',
    'blue' => 'adj', 'brave' => 'adj', 'bright' => 'adj', 'busy' => 'adj',
    'calm' => 'adj', 'careful' => 'adj', 'clean' => 'adj', 'clear' => 'adj',
    'clever' => 'adj', 'cloudy' => 'adj', 'cold' => 'adj', 'cool' => 'adj',
    'dark' => 'adj', 'deep' => 'adj', 'dirty' => 'adj', 'dry' => 'adj',
    'easy' => 'adj', 'empty' => 'adj', 'excited' => 'adj', 'fast' => 'adj',
    'fine' => 'adj', 'free' => 'adj', 'fresh' => 'adj', 'full' => 'adj',
    'funny' => 'adj', 'glad' => 'adj', 'good' => 'adj', 'great' => 'adj',
    'green' => 'adj', 'happy' => 'adj', 'hard' => 'adj', 'heavy' => 'adj',
    'high' => 'adj', 'hot' => 'adj', 'hungry' => 'adj', 'important' => 'adj',
    'kind' => 'adj', 'large' => 'adj', 'late' => 'adj', 'light' => 'adj',
    'little' => 'adj', 'loud' => 'adj', 'low' => 'adj', 'lucky' => 'adj',
    'new' => 'adj', 'nice' => 'adj', 'old' => 'adj', 'open' => 'adj',
    'orange' => 'adj', 'pretty' => 'adj', 'proud' => 'adj', 'quick' => 'adj',
    'quiet' => 'adj', 'ready' => 'adj', 'red' => 'adj', 'rich' => 'adj',
    'right' => 'adj', 'round' => 'adj', 'safe' => 'adj', 'short' => 'adj',
    'sick' => 'adj', 'silly' => 'adj', 'simple' => 'adj', 'slow' => 'adj',
    'small' => 'adj', 'soft' => 'adj', 'strong' => 'adj', 'sure' => 'adj',
    'sweet' => 'adj', 'tall' => 'adj', 'thick' => 'adj', 'thin' => 'adj',
    'tired' => 'adj', 'tough' => 'adj', 'true' => 'adj', 'warm' => 'adj',
    'weak' => 'adj', 'wet' => 'adj', 'white' => 'adj', 'wide' => 'adj',
    'wonderful' => 'adj', 'young' => 'adj', 'yellow' => 'adj',

    // --- adverbs ---
    'always' => 'adv', 'away' => 'adv', 'carefully' => 'adv', 'early' => 'adv',
    'easily' => 'adv', 'everywhere' => 'adv', 'finally' => 'adv', 'generally' => 'adv',
    'gently' => 'adv', 'here' => 'adv', 'happily' => 'adv', 'hardly' => 'adv',
    'inside' => 'adv', 'late' => 'adv', 'loudly' => 'adv', 'now' => 'adv',
    'often' => 'adv', 'outside' => 'adv', 'quickly' => 'adv', 'quietly' => 'adv',
    'slowly' => 'adv', 'sometimes' => 'adv', 'soon' => 'adv', 'suddenly' => 'adv',
    'there' => 'adv', 'today' => 'adv', 'together' => 'adv', 'tomorrow' => 'adv',
    'very' => 'adv', 'well' => 'adv', 'yesterday' => 'adv', 'again' => 'adv',
    'already' => 'adv', 'back' => 'adv', 'enough' => 'adv', 'almost' => 'adv',

    // --- function words (safe, clean connectors) ---
    'about' => 'function', 'after' => 'function', 'against' => 'function',
    'along' => 'function', 'among' => 'function', 'around' => 'function',
    'before' => 'function', 'behind' => 'function', 'below' => 'function',
    'beside' => 'function', 'between' => 'function', 'beyond' => 'function',
    'during' => 'function', 'except' => 'function', 'for' => 'function',
    'from' => 'function', 'inside' => 'function', 'into' => 'function',
    'near' => 'function', 'off' => 'function', 'onto' => 'function',
    'outside' => 'function', 'over' => 'function', 'past' => 'function',
    'through' => 'function', 'toward' => 'function', 'under' => 'function',
    'until' => 'function', 'upon' => 'function', 'with' => 'function',
    'within' => 'function', 'without' => 'function',
];
