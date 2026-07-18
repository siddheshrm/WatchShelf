<?php
$watchQuotes = [
    "The two most powerful warriors are patience and time. - Leo Tolstoy",
    "Time is what we want most, but what we use worst. - William Penn",
    "Lost time is never found again. - Benjamin Franklin",
    "Time is money. - Benjamin Franklin",
    "Time waits for no one. - Traditional proverb.",
    "Punctuality is the thief of time. - Oscar Wilde",
    "Time is the wisest counselor of all. - Pericles",
    "Better three hours too soon than a minute too late. - William Shakespeare",
    "The key is in not spending time, but in investing it. - Stephen R. Covey",
    "Time flies over us, but leaves its shadow behind. - Nathaniel Hawthorne",
    "Time is the most valuable thing a man can spend. - Theophrastus",
    "Time discovers truth. - Seneca the Younger",
    "Time heals what reason cannot. - Seneca the Younger",
    "They always say time changes things, but you actually have to change them yourself. - Andy Warhol",
    "Don't watch the clock; do what it does. Keep going. - Sam Levenson",
    "Time is the coin of your life. It is the only coin you have. - Carl Sandburg",
    "The future depends on what you do today. - Mahatma Gandhi",
    "Yesterday is gone. Tomorrow has not yet come. We have only today. - Mother Teresa",
    "An inch of time is an inch of gold, but you can't buy that inch of time with an inch of gold. - Traditional Chinese proverb.",
    "Time and tide wait for no man. - Traditional English proverb.",
    "The bad news is time flies. The good news is you're the pilot. - Michael Altshuler",
    "Time is the longest distance between two places. - Tennessee Williams",
    "The butterfly counts not months but moments, and has time enough. - Rabindranath Tagore",
    "Forever is composed of nows. - Emily Dickinson",
    "Nothing is a waste of time if you use the experience wisely. - Auguste Rodin",
    "Time brings all things to pass. - Aeschylus",
    "To choose time is to save time. - Francis Bacon",
    "There is more to life than increasing its speed. - Mahatma Gandhi",
    "Everything comes to him who hustles while he waits. - Thomas A. Edison",
    "Time is the school in which we learn; time is the fire in which we burn. - Delmore Schwartz",
    "No hour of life is wasted that is spent in the saddle. - Winston Churchill"
];

// Quote of the day (1st -> first quote, 31st -> last quote)
$quoteOfTheDay = $watchQuotes[(int) date('j') - 1];

echo "<p>$quoteOfTheDay</p>";
?>