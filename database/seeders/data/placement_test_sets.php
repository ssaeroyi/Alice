<?php

/*
|--------------------------------------------------------------------------
| Placement Test Sets (seed-ready)
|--------------------------------------------------------------------------
|
| Each set is one coherent placement test: 10 vocabulary items, a
| 10-blank cloze passage, and a comprehension passage with 5 questions
| (25 scored items per attempt).
|
| A student is assigned ONE set per placement attempt (randomized). All
| passages are adapted from public-domain classics and rewritten for
| grades 7-10 readability. Attribution is shown in the UI and stored
| here so PlacementSeeder can consume this file directly.
|
| Consumers: database/seeders/PlacementSeeder.php
| Views: resources/views/placement/step-2-vocab, step-3-cloze,
|        step-4-comprehension (dynamic; options are shuffled per attempt).
|
| License note: all sources are public domain (Project Gutenberg).
*/

return [

    'sets' => [

        [
            'id' => 1,
            'slug' => 'ant-and-the-grasshopper',
            'title' => 'The Ant and the Grasshopper',
            'author' => 'Aesop',
            'license' => 'Public domain',
            'source_url' => 'https://www.gutenberg.org/ebooks/21',

            'vocab' => [
                ['word' => 'meadow', 'options' => ['A field of grass', 'A tall mountain', 'A busy city street', 'A deep ocean'], 'answer' => 'A field of grass'],
                ['word' => 'grain', 'options' => ['A small seed of wheat or rice', 'A heavy rock', 'A winter coat', 'A wooden tool'], 'answer' => 'A small seed of wheat or rice'],
                ['word' => 'harvest', 'options' => ['The season when crops are gathered', 'A sudden storm', 'A type of bird', 'A quiet hour at night'], 'answer' => 'The season when crops are gathered'],
                ['word' => 'plenty', 'options' => ['A large enough amount', 'A small shortage', 'A sudden noise', 'A hidden place'], 'answer' => 'A large enough amount'],
                ['word' => 'shiver', 'options' => ['To shake slightly from the cold', 'To walk with long steps', 'To shout for help', 'To sleep in the sun'], 'answer' => 'To shake slightly from the cold'],
                ['word' => 'leisure', 'options' => ['Free time to relax', 'Heavy work', 'A locked door', 'A loud argument'], 'answer' => 'Free time to relax'],
                ['word' => 'request', 'options' => ['A polite ask for something', 'A quick command', 'A broken promise', 'A sudden change'], 'answer' => 'A polite ask for something'],
                ['word' => 'remind', 'options' => ['To help someone remember', 'To push something away', 'To fix a broken item', 'To hide from danger'], 'answer' => 'To help someone remember'],
                ['word' => 'value', 'options' => ['How important or useful something is', 'How heavy something is', 'How fast something moves', 'How old something looks'], 'answer' => 'How important or useful something is'],
                ['word' => 'careless', 'options' => ['Not paying enough attention', 'Extremely generous', 'Very courageous', 'Quietly cheerful'], 'answer' => 'Not paying enough attention'],
            ],

            'cloze' => [
                'text' => 'In a sunny meadow, a grasshopper spent the warm days {{1}} and leaping from blade to blade. Nearby, an ant worked {{2}}, carrying grains of wheat to her nest. "Why work so hard?" the grasshopper asked. "There is plenty of time to rest." The ant replied, "I am {{3}} for the cold days ahead." When winter arrived, snow covered the ground and food was {{4}}. The hungry grasshopper knocked on the ant\'s door, but she reminded him of his careless days. He {{5}} that a little preparation each day would have saved him. When spring returned, the grasshopper kept his promise. He worked {{6}} beside the ant, gathering grain with care. Some days his legs ached and he wanted to {{7}}, but the ant cheered him on. By the next winter their store was full, and the meadow rang with song once more. The grasshopper finally understood how work and play can {{8}} each other, and he thanked the friend who had been so {{9}} with him. Never again did he {{10}} winter catch him unprepared.',
                'blanks' => [
                    ['key' => 1, 'options' => ['singing', 'sleeping', 'hiding'], 'answer' => 'singing'],
                    ['key' => 2, 'options' => ['steadily', 'lazily', 'carelessly'], 'answer' => 'steadily'],
                    ['key' => 3, 'options' => ['preparing', 'hoping', 'praying'], 'answer' => 'preparing'],
                    ['key' => 4, 'options' => ['scarce', 'plentiful', 'ripe'], 'answer' => 'scarce'],
                    ['key' => 5, 'options' => ['realized', 'doubted', 'boasted'], 'answer' => 'realized'],
                    ['key' => 6, 'options' => ['faithfully', 'angrily', 'sleepily'], 'answer' => 'faithfully'],
                    ['key' => 7, 'options' => ['give up', 'give thanks', 'give orders'], 'answer' => 'give up'],
                    ['key' => 8, 'options' => ['balance', 'replace', 'separate'], 'answer' => 'balance'],
                    ['key' => 9, 'options' => ['patient', 'jealous', 'fearful'], 'answer' => 'patient'],
                    ['key' => 10, 'options' => ['let', 'kept', 'sent'], 'answer' => 'let'],
                ],
            ],

            'comprehension' => [
                'passage' => 'In the warm days of summer, a grasshopper spent his hours hopping and singing, certain that winter would never come. An ant, however, worked from sunrise to sunset, carrying grain back to her nest. "You waste your days," the ant warned. "When winter arrives, you will have nothing." The grasshopper laughed and sang louder. When winter came, snow buried the fields, and the grasshopper grew cold and hungry. He went to the ant\'s door, but there was no food to spare. The ant had prepared, and the grasshopper had not. He learned that day that comfort comes from preparation, not from hope alone.',
                'questions' => [
                    ['type' => 'literal', 'prompt' => 'What did the ant do during the summer?', 'options' => ['Carried grain to her nest', 'Sang in the meadow', 'Slept through the days', 'Traveled to a new land'], 'answer' => 'Carried grain to her nest'],
                    ['type' => 'inferential', 'prompt' => 'Why did the grasshopper sing louder after the ant warned him?', 'options' => ['He believed winter would never come and did not take the warning seriously', 'He was singing for a contest', 'He wanted to wake the ant', 'He had already stored enough food'], 'answer' => 'He believed winter would never come and did not take the warning seriously'],
                    ['type' => 'vocabulary', 'word' => 'comfort', 'prompt' => 'In the passage, what does the word comfort mean?', 'options' => ['A state of ease and freedom from worry', 'A heavy burden', 'A sudden surprise', 'A loud celebration'], 'answer' => 'A state of ease and freedom from worry'],
                    ['type' => 'literal', 'prompt' => 'What happened to the grasshopper when winter came?', 'options' => ['He grew cold and hungry', 'He flew south with the birds', 'He moved into the ant\'s nest', 'He found plenty of food under the snow'], 'answer' => 'He grew cold and hungry'],
                    ['type' => 'inferential', 'prompt' => 'What does the passage suggest about hope alone?', 'options' => ['It is not enough without preparation', 'It always brings comfort', 'It matters more than work', 'It is only for the lazy'], 'answer' => 'It is not enough without preparation'],
                ],
            ],
        ],

        [
            'id' => 2,
            'slug' => 'the-wind-in-the-willows',
            'title' => 'The Wind in the Willows',
            'author' => 'Kenneth Grahame',
            'license' => 'Public domain',
            'source_url' => 'https://www.gutenberg.org/ebooks/289',

            'vocab' => [
                ['word' => 'burrow', 'options' => ['A hole dug in the ground by an animal', 'A tall bridge', 'A fishing net', "A farmer's cart"], 'answer' => 'A hole dug in the ground by an animal'],
                ['word' => 'bank', 'options' => ['The ground beside a river', 'A place to store coins', 'A row of seats', 'A loud sound'], 'answer' => 'The ground beside a river'],
                ['word' => 'hollow', 'options' => ['Having an empty space inside', 'Covered all over in gold', 'Very heavy to lift', 'Bright and full of light'], 'answer' => 'Having an empty space inside'],
                ['word' => 'scramble', 'options' => ['To climb or move quickly and awkwardly', 'To walk slowly in a line', 'To sing in a low voice', 'To rest in the shade'], 'answer' => 'To climb or move quickly and awkwardly'],
                ['word' => 'declare', 'options' => ['To say something clearly and firmly', 'To hide something quietly', 'To forget a promise', 'To paint a picture'], 'answer' => 'To say something clearly and firmly'],
                ['word' => 'wander', 'options' => ['To walk without a fixed direction', 'To run in a race', 'To sit perfectly still', 'To dig a deep hole'], 'answer' => 'To walk without a fixed direction'],
                ['word' => 'gentle', 'options' => ['Soft and kind', 'Loud and rough', 'Quick and sharp', 'Heavy and cold'], 'answer' => 'Soft and kind'],
                ['word' => 'scent', 'options' => ['A pleasant smell', 'A loud shout', 'A bright color', 'A soft touch'], 'answer' => 'A pleasant smell'],
                ['word' => 'cheerful', 'options' => ['Happy and bright', 'Tired and dull', 'Angry and loud', 'Quiet and shy'], 'answer' => 'Happy and bright'],
                ['word' => 'glide', 'options' => ['To move smoothly and easily', 'To jump very high', 'To fall suddenly', 'To spin in circles'], 'answer' => 'To move smoothly and easily'],
            ],

            'cloze' => [
                'text' => 'The Mole had been cleaning his little home all morning, working hard with his small paws. Spring was in the air, and even inside his dark burrow, he could feel its {{1}}. Suddenly he flung down his brush and declared, "{{2}}!" He scrambled up into the bright air, and the morning light {{3}} him warmly. The air was {{4}} with the songs of birds, and a little stream {{5}} past the riverbank, full of soft, glad sounds. Down by the water someone called a greeting, and the Mole turned to see a brown face with {{6}} eyes twinkling. It was the Water Rat, and he lost no time in {{7}} a little boat. "A boat," he said, "is the finest thing in the world." Simply messing about on the river, he explained, fills the hours {{8}}. The Mole climbed in, though his legs trembled a little, and soon every ripple of the {{9}} delighted him. That golden afternoon, he felt sure, would change his whole {{10}}.',
                'blanks' => [
                    ['key' => 1, 'options' => ['warmth', 'sadness', 'silence'], 'answer' => 'warmth'],
                    ['key' => 2, 'options' => ['Enough!', 'Hello!', 'Careful!'], 'answer' => 'Enough!'],
                    ['key' => 3, 'options' => ['welcomed', 'pushed', 'warned'], 'answer' => 'welcomed'],
                    ['key' => 4, 'options' => ['filled', 'emptied', 'covered'], 'answer' => 'filled'],
                    ['key' => 5, 'options' => ['flowed', 'froze', 'stopped'], 'answer' => 'flowed'],
                    ['key' => 6, 'options' => ['bright', 'closed', 'sleepy'], 'answer' => 'bright'],
                    ['key' => 7, 'options' => ['fetching', 'breaking', 'selling'], 'answer' => 'fetching'],
                    ['key' => 8, 'options' => ['perfectly', 'badly', 'loudly'], 'answer' => 'perfectly'],
                    ['key' => 9, 'options' => ['stream', 'desert', 'highway'], 'answer' => 'stream'],
                    ['key' => 10, 'options' => ['life', 'clothes', 'lunch'], 'answer' => 'life'],
                ],
            ],

            'comprehension' => [
                'passage' => 'The Mole worked all morning cleaning his small home underground. The spring air reached even his dark burrow and made him restless. At last he threw down his brush and pushed his way out into the sunshine. The meadow smelled fresh, and everything around him seemed to be moving and growing. When he reached the river, he stopped in wonder. He had never seen a river before. It twisted and gleamed in the sunlight, chattering to itself as it ran. Mole stood at the water\'s edge, watching and listening, and he knew at once that this was the finest thing he had ever met.',
                'questions' => [
                    ['type' => 'literal', 'prompt' => 'What was Mole doing at the start of the passage?', 'options' => ['Cleaning his home underground', 'Swimming in the river', 'Visiting a friend', 'Eating a meal'], 'answer' => 'Cleaning his home underground'],
                    ['type' => 'inferential', 'prompt' => 'Why did Mole stop in wonder when he reached the river?', 'options' => ['He had never seen a river before and found it beautiful and new', 'He was too tired to walk further', 'He was afraid of the water', 'He heard a loud noise'], 'answer' => 'He had never seen a river before and found it beautiful and new'],
                    ['type' => 'vocabulary', 'word' => 'restless', 'prompt' => 'In the passage, what does the word restless mean?', 'options' => ['Unable to stay still or quiet', 'Deeply asleep', 'Very hungry', 'Completely satisfied'], 'answer' => 'Unable to stay still or quiet'],
                    ['type' => 'inferential', 'prompt' => 'What can you tell about Mole\'s life before this day?', 'options' => ['He had spent most of it underground, away from rivers', 'He had sailed rivers many times before', 'He was born beside the riverbank', 'He disliked all animals'], 'answer' => 'He had spent most of it underground, away from rivers'],
                    ['type' => 'literal', 'prompt' => 'What greeted Mole when he pushed out into the sunshine?', 'options' => ['Fresh smells and everything moving and growing', 'Heavy rain and a cold wind', 'A silent, empty field', 'A crowd of noisy farmers'], 'answer' => 'Fresh smells and everything moving and growing'],
                ],
            ],
        ],

        [
            'id' => 3,
            'slug' => 'mowglis-brothers',
            'title' => 'Mowgli\'s Brothers (The Jungle Book)',
            'author' => 'Rudyard Kipling',
            'license' => 'Public domain',
            'source_url' => 'https://www.gutenberg.org/ebooks/236',

            'vocab' => [
                ['word' => 'lair', 'options' => ["A wild animal's resting place", 'A tall tree', 'A swift river', 'A sharp stone'], 'answer' => "A wild animal's resting place"],
                ['word' => 'pounce', 'options' => ['To jump suddenly to catch something', 'To walk slowly home', 'To swim across a lake', 'To sing a soft song'], 'answer' => 'To jump suddenly to catch something'],
                ['word' => 'cub', 'options' => ['A young wild animal', 'A large container', 'A fallen branch', 'A bright star'], 'answer' => 'A young wild animal'],
                ['word' => 'pack', 'options' => ['A group of animals that hunt together', 'A pile of rocks', 'A wooden fence', "A farmer's field"], 'answer' => 'A group of animals that hunt together'],
                ['word' => 'creature', 'options' => ['Any living animal', 'A distant storm', 'A metal tool', 'A narrow path'], 'answer' => 'Any living animal'],
                ['word' => 'fierce', 'options' => ['Frightening and aggressive', 'Calm and quiet', 'Small and weak', 'Slow and tired'], 'answer' => 'Frightening and aggressive'],
                ['word' => 'cunning', 'options' => ['Clever in a tricky way', 'Honest and open', 'Large and heavy', 'Young and playful'], 'answer' => 'Clever in a tricky way'],
                ['word' => 'shelter', 'options' => ['A safe place to stay', 'A loud warning', 'A heavy meal', 'A long journey'], 'answer' => 'A safe place to stay'],
                ['word' => 'instinct', 'options' => ['A natural feeling that guides behavior', 'A careful plan', 'A written rule', 'A loud complaint'], 'answer' => 'A natural feeling that guides behavior'],
                ['word' => 'loyal', 'options' => ['Faithful and devoted', 'Angry and rude', 'Quick and noisy', 'Strange and distant'], 'answer' => 'Faithful and devoted'],
            ],

            'cloze' => [
                'text' => 'Father Wolf woke and stretched, ready for the night\'s {{1}}. Outside, the moon silvered the jungle, and Mother Wolf kept watch from the cave\'s {{2}}. That night, something small and helpless came to the cave\'s mouth: a man\'s cub. Mother Wolf looked at the tiny creature and {{3}} to raise him as her own. Under her care, the cub {{4}} and learned the ways of the jungle, for the pack accepted him as {{5}}. Seasons passed, and the boy ran beside the wolf cubs, quick as any of them. He learned the {{6}} of the jungle: which berries were sweet, which paths led to the river, and when to lie {{7}} while danger passed. Only Shere Khan the tiger growled that a man\'s cub had no place among wolves. But Mother Wolf stood {{8}} and answered that her cub would live. The pack remembered the law of the jungle and agreed with her. And so the man\'s cub grew up {{9}} and free, unafraid of the dark, for the wolves who raised him stayed {{10}} to him always.',
                'blanks' => [
                    ['key' => 1, 'options' => ['hunt', 'rest', 'storm'], 'answer' => 'hunt'],
                    ['key' => 2, 'options' => ['entrance', 'roof', 'floor'], 'answer' => 'entrance'],
                    ['key' => 3, 'options' => ['chose', 'refused', 'failed'], 'answer' => 'chose'],
                    ['key' => 4, 'options' => ['thrived', 'shrunk', 'slept'], 'answer' => 'thrived'],
                    ['key' => 5, 'options' => ['family', 'enemy', 'stranger'], 'answer' => 'family'],
                    ['key' => 6, 'options' => ['secrets', 'songs', 'shadows'], 'answer' => 'secrets'],
                    ['key' => 7, 'options' => ['still', 'loud', 'tall'], 'answer' => 'still'],
                    ['key' => 8, 'options' => ['firm', 'aside', 'away'], 'answer' => 'firm'],
                    ['key' => 9, 'options' => ['strong', 'silent', 'sorry'], 'answer' => 'strong'],
                    ['key' => 10, 'options' => ['true', 'cruel', 'cold'], 'answer' => 'true'],
                ],
            ],

            'comprehension' => [
                'passage' => 'Deep in the jungle, a wolf family woke as night fell. While Father Wolf stretched and prepared to hunt, something unusual appeared at the mouth of their cave: a small, unsteady child, holding onto a low branch. Mother Wolf studied the man\'s cub with careful eyes. Among wolves, a law said that the cub of a man may be protected by a pack. She pushed the child gently among her own cubs, and the tiny creature settled down to sleep beside them. From that moment, the wolves raised the boy as one of their own, and he grew strong in the heart of the jungle.',
                'questions' => [
                    ['type' => 'literal', 'prompt' => 'What appeared at the mouth of the wolves\' cave?', 'options' => ['A small, unsteady child', 'A hunting tiger', 'A sleeping deer', 'A falling tree'], 'answer' => 'A small, unsteady child'],
                    ['type' => 'inferential', 'prompt' => 'Why did the wolves accept the man\'s cub?', 'options' => ['A law of the jungle allowed the pack to protect him', 'They needed a human to guide them', 'The child offered them food', 'The tiger ordered them to'], 'answer' => 'A law of the jungle allowed the pack to protect him'],
                    ['type' => 'vocabulary', 'word' => 'unsteady', 'prompt' => 'In the passage, what does the word unsteady mean?', 'options' => ['Shaky and not firm', 'Fast and strong', 'Bright and shiny', 'Quiet and hidden'], 'answer' => 'Shaky and not firm'],
                    ['type' => 'literal', 'prompt' => 'Where did Mother Wolf place the sleeping child?', 'options' => ['Among her own cubs', 'Outside near the trees', 'By the riverbank', 'High up on a rock'], 'answer' => 'Among her own cubs'],
                    ['type' => 'inferential', 'prompt' => 'What does the passage show about Mother Wolf?', 'options' => ['She was protective and caring toward the child', 'She feared the child deeply', 'She wanted to send him away at once', 'She paid no attention to him'], 'answer' => 'She was protective and caring toward the child'],
                ],
            ],
        ],

        [
            'id' => 4,
            'slug' => 'the-secret-garden',
            'title' => 'The Secret Garden',
            'author' => 'Frances Hodgson Burnett',
            'license' => 'Public domain',
            'source_url' => 'https://www.gutenberg.org/ebooks/17396',

            'vocab' => [
                ['word' => 'chimney', 'options' => ['A pipe that carries smoke from a fire out of a house', 'A window with painted glass', 'A door left open all day', 'A roof made of straw'], 'answer' => 'A pipe that carries smoke from a fire out of a house'],
                ['word' => 'moors', 'options' => ['Wide open areas of rough land', 'Long stone walls', 'Quiet village streets', 'Deep forest paths'], 'answer' => 'Wide open areas of rough land'],
                ['word' => 'sparrow', 'options' => ['A common small brownish bird', 'A large white farm bird', 'A bird that cannot fly', 'A bird that lives at sea'], 'answer' => 'A common small brownish bird'],
                ['word' => 'moss', 'options' => ['Soft green growth that covers damp stones', 'A thorny desert bush', 'A tall forest tree', 'A bright garden flower'], 'answer' => 'Soft green growth that covers damp stones'],
                ['word' => 'shabby', 'options' => ['Old and worn-out looking', 'Freshly cleaned and new', 'Strong and expensive', 'Hidden behind a wall'], 'answer' => 'Old and worn-out looking'],
                ['word' => 'buried', 'options' => ['Held high above', 'Painted in bright colors', 'Covered completely over', 'Marked with a sign'], 'answer' => 'Covered completely over'],
                ['word' => 'sprout', 'options' => ['To begin to grow from a seed', 'To dry up completely', 'To fall off a branch', 'To change color in autumn'], 'answer' => 'To begin to grow from a seed'],
                ['word' => 'weed', 'options' => ['A heavy garden tool', 'An unwanted plant growing wild', 'A shallow pool of water', 'A kind of grass used for roofs'], 'answer' => 'An unwanted plant growing wild'],
                ['word' => 'eager', 'options' => ['Tired and sleepy', 'Quiet and nervous', 'Very excited and wanting to do something', 'Cold and uncomfortable'], 'answer' => 'Very excited and wanting to do something'],
                ['word' => 'secret', 'options' => ['Kept hidden from others', 'Widely known by everyone', 'Given away for free', 'Written on every wall'], 'answer' => 'Kept hidden from others'],
            ],

            'cloze' => [
                'text' => 'After her parents died, Mary Lennox was sent to a great {{1}} on the Yorkshire moors. The house felt cold and empty, and Mary had no friends. One morning she followed a {{2}} through the gardens and noticed a wall covered in thick {{3}}. Behind it lay a garden that had been {{4}} for ten years. Mary decided to bring it back to life, and as the first flowers began to {{5}}, Mary herself began to change. Each morning she hurried out with a {{6}} and a small fork, clearing the tangled beds. A boy named Dickon, who could charm birds with his whistle, came to help her, bringing seeds and {{7}}. Day by day the roses were pruned, the weeds were pulled, and green shoots {{8}} up through the dark soil. The robin watched it all from the wall, as pleased as if the plan had been its {{9}} idea. Little by little the hidden garden woke, and with it woke a girl no one had expected to see {{10}}.',
                'blanks' => [
                    ['key' => 1, 'options' => ['market', 'manor', 'mountain'], 'answer' => 'manor'],
                    ['key' => 2, 'options' => ['robin', 'rabbit', 'raven'], 'answer' => 'robin'],
                    ['key' => 3, 'options' => ['paint', 'smoke', 'ivy'], 'answer' => 'ivy'],
                    ['key' => 4, 'options' => ['repaired', 'abandoned', 'crowded'], 'answer' => 'abandoned'],
                    ['key' => 5, 'options' => ['rust', 'crumble', 'bloom'], 'answer' => 'bloom'],
                    ['key' => 6, 'options' => ['basket', 'hammer', 'lantern'], 'answer' => 'basket'],
                    ['key' => 7, 'options' => ['tools', 'tickets', 'toys'], 'answer' => 'tools'],
                    ['key' => 8, 'options' => ['pushed', 'melted', 'flew'], 'answer' => 'pushed'],
                    ['key' => 9, 'options' => ['own', 'enemy', 'burden'], 'answer' => 'own'],
                    ['key' => 10, 'options' => ['smile', 'sleep', 'leave'], 'answer' => 'smile'],
                ],
            ],

            'comprehension' => [
                'passage' => 'Mary Lennox was a lonely girl. After the death of her parents, she was sent to live at Misselthwaite Manor, a great house on the wide Yorkshire moors. The house had over a hundred rooms, most of them locked. Mary had nothing to do and no one to talk to. One day she wandered into the gardens and saw a robin hopping along a path. The bird led her to a wall buried under thick ivy. Behind the ivy was a hidden garden, locked and deserted for ten years. The roses were tangled, and the weeds had taken over. Yet Mary did not feel discouraged. She found a key, cleared the weeds, and cut away the ivy. Little by little the garden woke up, and so did Mary. She learned that a place left alone can grow wild, but a place cared for can grow beautiful.',
                'questions' => [
                    ['type' => 'literal', 'prompt' => 'What did Mary find behind the ivy-covered wall?', 'options' => ['A busy market', 'A second house', 'A hidden garden, locked for ten years', 'A lake full of fish'], 'answer' => 'A hidden garden, locked for ten years'],
                    ['type' => 'inferential', 'prompt' => 'Why did Mary change as the garden came back to life?', 'options' => ['Caring for the garden gave her purpose and joy', 'She wanted to impress the gardener', 'The garden was worth a lot of money', 'She was forced to work every day'], 'answer' => 'Caring for the garden gave her purpose and joy'],
                    ['type' => 'vocabulary', 'word' => 'deserted', 'prompt' => 'In the passage, what does the word deserted mean?', 'options' => ['Filled with people', 'Left empty and abandoned', 'Freshly painted', 'Very bright'], 'answer' => 'Left empty and abandoned'],
                    ['type' => 'inferential', 'prompt' => 'What does the passage suggest about neglected places?', 'options' => ['With care, even forgotten places can grow beautiful again', 'Neglected places can never recover', 'Locked gardens are best left shut forever', 'Weeds are always stronger than people'], 'answer' => 'With care, even forgotten places can grow beautiful again'],
                    ['type' => 'literal', 'prompt' => 'What does the passage say about the rooms of Misselthwaite Manor?', 'options' => ['There were over a hundred, and most of them were locked', 'There were exactly ten, all open to Mary', 'There were only three small rooms', 'The rooms were all bright and full of visitors'], 'answer' => 'There were over a hundred, and most of them were locked'],
                ],
            ],
        ],

        [
            'id' => 5,
            'slug' => 'peter-pan',
            'title' => 'Peter Pan',
            'author' => 'J. M. Barrie',
            'license' => 'Public domain',
            'source_url' => 'https://www.gutenberg.org/ebooks/16',

            'vocab' => [
                ['word' => 'curtain', 'options' => ['Hanging cloth used to cover a window', 'A soft rug for the floor', 'A lamp that glows at night', 'A box for keeping toys'], 'answer' => 'Hanging cloth used to cover a window'],
                ['word' => 'shadow', 'options' => ['A gentle breeze', 'A dark shape made by light', 'A quiet whisper', 'A thin curtain'], 'answer' => 'A dark shape made by light'],
                ['word' => 'fairy', 'options' => ['A small magical creature with wings', 'A tiny sailing boat', 'A bright firefly', 'A baby bird'], 'answer' => 'A small magical creature with wings'],
                ['word' => 'mischief', 'options' => ['Careful planning', 'Playful behavior that causes trouble', 'Quiet obedience', 'Hard physical work'], 'answer' => 'Playful behavior that causes trouble'],
                ['word' => 'adventure', 'options' => ['A long afternoon nap', 'An exciting and risky experience', 'A heavy schoolbag', 'A slow walk home'], 'answer' => 'An exciting and risky experience'],
                ['word' => 'brave', 'options' => ['Showing courage in the face of danger', 'Feeling tired and heavy', 'Moving very slowly', 'Speaking very softly'], 'answer' => 'Showing courage in the face of danger'],
                ['word' => 'crew', 'options' => ['The people who work on a ship', 'A storm at sea', 'A map of hidden treasure', 'The front of a boat'], 'answer' => 'The people who work on a ship'],
                ['word' => 'lagoon', 'options' => ['A steep rocky hill', 'A calm body of water near the sea', 'A dark forest clearing', 'A busy city street'], 'answer' => 'A calm body of water near the sea'],
                ['word' => 'capture', 'options' => ['To catch and hold someone', 'To guide someone home', 'To free someone early', 'To warn someone of danger'], 'answer' => 'To catch and hold someone'],
                ['word' => 'wander', 'options' => ['To sit perfectly still', 'To run in a race', 'To move about without a clear direction', 'To sleep through the day'], 'answer' => 'To move about without a clear direction'],
            ],

            'cloze' => [
                'text' => 'One night, while Mr. and Mrs. Darling were out, a strange boy flew into the {{1}}. His name was Peter Pan, and he never grew up. Peter taught the three children to fly by thinking {{2}} thoughts and sprinkling a little fairy dust. "{{3}} to the second star and straight on till morning!" he called. They soared out of the window and over the sleeping city toward {{4}}. Wendy was the first to {{5}} Peter\'s promise of endless adventures. Higher and higher they flew, past towers and steeples, until the great city below seemed {{6}} and far away. In Neverland the children swam in a lagoon where mermaids combed their hair, and they kept careful watch for Captain Hook, the most feared {{7}} of them all. There were picnics under strange trees and narrow escapes won by a whisker. Wendy looked after the Lost Boys, sewing pockets and telling stories, while John and Michael forgot their lessons {{8}}. Yet some nights Wendy grew quiet, for even the boldest explorer misses a mother\'s {{9}}. In the end, the nursery window stayed open, because every adventure is sweetest when home is waiting at its {{10}}.',
                'blanks' => [
                    ['key' => 1, 'options' => ['kitchen', 'nursery', 'garden'], 'answer' => 'nursery'],
                    ['key' => 2, 'options' => ['heavy', 'funny', 'happy'], 'answer' => 'happy'],
                    ['key' => 3, 'options' => ['Run', 'Hide', 'Fly'], 'answer' => 'Fly'],
                    ['key' => 4, 'options' => ['School', 'London', 'Neverland'], 'answer' => 'Neverland'],
                    ['key' => 5, 'options' => ['break', 'forget', 'trust'], 'answer' => 'trust'],
                    ['key' => 6, 'options' => ['small', 'near', 'loud'], 'answer' => 'small'],
                    ['key' => 7, 'options' => ['pirate', 'doctor', 'baker'], 'answer' => 'pirate'],
                    ['key' => 8, 'options' => ['entirely', 'slowly', 'gently'], 'answer' => 'entirely'],
                    ['key' => 9, 'options' => ['love', 'money', 'homework'], 'answer' => 'love'],
                    ['key' => 10, 'options' => ['end', 'middle', 'price'], 'answer' => 'end'],
                ],
            ],

            'comprehension' => [
                'passage' => 'Wendy, John, and Michael lived in a house in London, in a room called the nursery. Their mother told them stories every night, and their father kept watch at the window with a book in his hand. One night, while their parents were out, a boy flew through the window. He was Peter Pan, the boy who never grew up, and he had lost his shadow. Wendy sewed his shadow back on for him. In return, Peter taught the children to fly. "Just think lovely thoughts," he said, "and sprinkle on a little fairy dust." Then the three children followed Peter across the night sky to Neverland, an island full of mermaids, pirates, and endless adventures. Wendy had been afraid to leave home, but the promise of adventure was too strong to resist.',
                'questions' => [
                    ['type' => 'literal', 'prompt' => 'How did Peter Pan enter the children\'s room?', 'options' => ['He climbed up the stairs', 'He flew in through the window', 'He knocked on the front door', 'He came down the chimney'], 'answer' => 'He flew in through the window'],
                    ['type' => 'inferential', 'prompt' => 'Why did Wendy sew Peter\'s shadow back on?', 'options' => ['She was kind and wanted to help him', 'She wanted him to leave quickly', 'Her father told her to', 'She hoped to sell it'], 'answer' => 'She was kind and wanted to help him'],
                    ['type' => 'vocabulary', 'word' => 'resist', 'prompt' => 'In the passage, what does the word resist mean?', 'options' => ['To agree quickly', 'To walk slowly', 'To look carefully', 'To fight against or hold back'], 'answer' => 'To fight against or hold back'],
                    ['type' => 'literal', 'prompt' => 'What did Peter teach the children to do?', 'options' => ['Fly with lovely thoughts and a little fairy dust', 'Swim across the mermaid lagoon', 'Sail a pirate ship alone', 'Build a house of leaves'], 'answer' => 'Fly with lovely thoughts and a little fairy dust'],
                    ['type' => 'inferential', 'prompt' => 'What does the passage suggest about Wendy\'s feelings about leaving home?', 'options' => ['She felt torn between fear and the pull of adventure', 'She felt pure joy without any doubt', 'She refused to leave at all', 'She was angry at her brothers for following Peter'], 'answer' => 'She felt torn between fear and the pull of adventure'],
                ],
            ],
        ],

    ],

];
