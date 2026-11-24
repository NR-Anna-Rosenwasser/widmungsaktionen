<?php

namespace App\Support;

/**
 * Little encouragements on the Widmen screen, every few books. Written for
 * an author whose books are read by young women and queer people, but
 * generic enough for anyone writing for a fairer world.
 */
class Motivation
{
    /** Books between two messages. */
    public const MIN_GAP = 5;

    public const MAX_GAP = 15;

    /** For how many books a message stays on screen. */
    public const SHOWN_FOR = 3;

    public const MESSAGES = [
        // Who's waiting for the book
        'Irgendwo wartet gerade jemand ungeduldig auf den Pöstler. Und zwar auf genau dieses Buch.',
        'Dieses Buch landet vielleicht auf einem Nachttisch, neben dem Bett von jemandem, der es gerade sehr braucht.',
        'Stell dir vor, wie jemand das Couvert aufreisst, das Buch aufschlägt und deine Schrift sieht. 💌',
        'Für manche wird diese Widmung das Erste sein, was sie morgens im Büchergestell anschauen.',
        'Jemand wird diese Seite fotografieren und in den Gruppenchat schicken. Mit sehr vielen Herzchen.',
        'Diese Widmung wird mal jemandem gezeigt werden: «Schau, das hat sie mir geschrieben.»',
        'Vielleicht ist das das erste Buch, das jemand mit Absicht ganz für sich allein gekauft hat.',
        'Irgendwo wird heute Abend jemand ein bisschen weniger einsam sein. Wegen eines Buches. Wegen dir.',
        'Dieses Buch reist in ein Zuhause, wo jemand gerade lernt, sich selbst gernzuhaben.',
        'Jemand hat sich überlegt, was in der Widmung stehen soll. Und jetzt schreibst du es. Wie schön ist das bitte?',
        'Ein paar dieser Bücher sind Geschenke. Jemand wollte einem Menschen sagen: «Ich sehe dich.»',
        'Für irgendein queeres Kid in einem kleinen Dorf ist dieses Buch ein Fenster in eine grössere Welt. 🌈',
        'Vielleicht liest das jemand heimlich unter der Bettdecke. Mit Taschenlampe. Wie früher.',
        'Jemand wird dieses Buch in zwanzig Jahren noch besitzen. Mit deiner Widmung drin.',
        'Diese Bücher werden ausgeliehen, weitergegeben, zerlesen. Das ist das schönste Schicksal für ein Buch.',
        'Jedes Buch auf diesem Stapel hat schon jetzt einen Menschen, der sich darauf freut.',
        'Irgendwo hat jemand dieses Buch bestellt und dabei leise «endlich» gesagt.',
        'Eine junge Frau wird dieses Buch lesen und merken: Ich bin nicht zu laut. Ich bin genau richtig laut.',
        'Für jemanden ist diese Widmung der Beweis: Die Person, die mir Mut macht, weiss, dass es mich gibt.',
        'Bald liegt dieses Buch in einem Rucksack, zwischen Velolicht und Znüni. Und kommt überall mit hin.',

        // Why it matters
        'Worte verändern Menschen. Und Menschen verändern die Welt. So einfach und so gross ist das.',
        'Jede Widmung ist ein kleines «Du gehörst dazu».',
        'Bücher haben schon Leben gerettet. Vielleicht auch eines von diesem Stapel.',
        'Was du schreibst, gibt Menschen Sprache für Dinge, die sie bisher nur gefühlt haben.',
        'Sichtbarkeit ist kein Luxus. Und du machst gerade ziemlich viele Menschen ein bisschen sichtbarer.',
        'Hoffnung ist eine Entscheidung. Und dieser Stapel ist voll davon.',
        'Mit jedem Buch wird die Welt ein ganz kleines bisschen weniger allein.',
        'Wer liest, versteht. Wer versteht, verändert. Weiter so.',
        'Du schreibst nicht nur Namen in Bücher. Du schreibst Menschen Mut zu.',
        'Gesellschaftlicher Wandel beginnt oft ganz leise. Zum Beispiel am Küchentisch, mit einem Buch.',
        'Jedes dieser Bücher ist ein kleiner Akt des Widerstands gegen Gleichgültigkeit.',
        'Politik passiert nicht nur im Parlament. Sie passiert auch hier, Buch für Buch.',
        'Jemand wird dank diesem Buch zum ersten Mal an eine Demo gehen. Merk dir das.',
        'Solidarität kann man nicht verschicken? Doch, offenbar in Couverts. 📚',
        'Die Welt braucht mehr Menschen, die anderen sagen, dass sie richtig sind, wie sie sind. Danke, dass du eine davon bist.',
        'Du gibst Menschen Worte, mit denen sie sich wehren können. Und Worte, mit denen sie sich trösten können.',
        'Hinter jeder Bestellung steckt ein Mensch, der sich gesehen fühlen möchte. Und gleich wird.',
        'Diese Bücher sind Brücken. Zu sich selbst und zu anderen.',
        'Was gestern noch mutig war, wird morgen normal sein. Auch wegen Büchern wie diesem.',
        'Ein Buch kann der Moment sein, in dem jemand merkt: Ich darf mehr wollen.',

        // Encouragement for the signing
        'Du machst das grossartig. Wirklich. ✨',
        'Deine Handschrift ist gerade auf dem Weg in die ganze Schweiz. Und vielleicht noch weiter.',
        'Schon wieder ein paar Bücher weiter. Du bist eine Widmungsmaschine. Eine sehr liebevolle.',
        'Jede Widmung ist ein Unikat. Genau wie die Menschen, die sie bekommen.',
        'Du schreibst heute Geschichte. Okay, Widmungen. Aber auch ein bisschen Geschichte.',
        'Zwischenstand: Die Welt ist um ein paar handgeschriebene Liebesbriefe reicher.',
        'Falls niemand es dir heute gesagt hat: Was du machst, ist wichtig.',
        'Die Bücher auf diesem Stapel freuen sich schon auf ihre Reise.',
        'Bei jedem Buch ein kleines bisschen Herz. Man merkt das, versprochen.',
        'Krumme Buchstaben? Macht nichts. Echte Handschrift darf echt aussehen.',
        'Wie viele Menschen hast du heute schon glücklich gemacht? Schau mal auf den Fortschrittsbalken. 💖',
        'Für viele bist du die Stimme, die ihnen früher gefehlt hat.',
        'Keine Eile. Jede Widmung ist es wert, dass du dir Zeit nimmst.',
        'Wenn Widmen ein Sport wäre, wärst du gerade sehr gut im Training.',
        'Ein Buch nach dem anderen. So geht Veränderung auch.',
        'Du bist näher am Ziel als vorhin. Das ist Mathe, aber auch ein gutes Gefühl.',
        'Jede Unterschrift ist ein kleines Versprechen: Ich bin auf deiner Seite.',
        'Die Post-its sind stolz auf dich. Die Bücher auch. Und wir sowieso.',
        'Diese Bücher werden geliebt werden. Und du hast gerade dafür gesorgt.',
        'Mit Herz und Kugelschreiber gegen die Ungerechtigkeit. Weiter so!',
        'Schau mal, wie weit du schon gekommen bist. Das warst alles du.',
        'Deine Schrift wird bald an sehr vielen Orten zu Hause sein.',
        'Du bist im Flow. Nicht stören lassen, ausser von Snacks.',
        'Noch ein Buch und noch eins. So entstehen Berge aus Liebe.',
        'Jedes Buch, das du widmest, ist eins weniger auf dem Stapel und eins mehr in der Welt.',
        'Du schenkst gerade etwas, das man nicht kaufen kann: deine Zeit.',
        'Der Stapel wird kleiner. Die Freude da draussen wird grösser.',
        'Das sieht nach sehr viel Herzblut aus. Und genau so soll es sein.',
        'Jedes Buch zählt. Auch dieses. Gerade dieses.',
        'Gut gemacht. Und das nächste wird auch gut.',
        'Dein Durchhaltevermögen ist beeindruckend. Ehrlich.',
        'Du schreibst nicht einfach Widmungen. Du verteilst kleine Umarmungen auf Papier. 🤗',
        'Konzentriert, liebevoll, unermüdlich: So sieht Widmen in Bestform aus.',
        'Jeder Name auf diesem Stapel wird heute ganz besonders ernst genommen. Von dir.',
        'Du hast schon so viel geschafft. Der Rest ist nur noch die Kür.',

        // Self-care
        'Kleine Erinnerung: Trink einen Schluck Wasser. 💧',
        'Schultern runter, tief durchatmen. Und dann weiter.',
        'Hand ein bisschen lockern? Kurz ausschütteln, dann gehts gleich besser.',
        'Eine Pause ist keine Schwäche. Drück einfach P, wenn du eine brauchst.',
        'Hast du heute schon etwas gegessen? Die Bücher warten gern einen Moment.',
        'Auch Revolutionär*innen brauchen Snacks. 🍫',
        'Sei heute so lieb zu dir, wie du es in diesen Widmungen zu anderen bist.',
        'Kurz aus dem Fenster schauen tut den Augen gut. Und dem Kopf.',
        'Du musst nicht alles heute schaffen. Aber schau, wie viel du schon geschafft hast.',
        'Zeit für einen Tee? Der Stapel läuft nicht davon. ☕',
        'Streck dich mal kurz. Ganz nach oben. Ja, genau so.',
        'Auch wer für eine bessere Welt kämpft, darf es sich gemütlich machen.',
        'Wie sitzt du gerade? Vielleicht ein Kissen in den Rücken? 🛋️',
        'Augen zu, bis drei zählen, Augen auf. Schon besser.',
        'Handgelenke kreisen lassen: einmal links, einmal rechts. Deine Hand dankt es dir.',
        'Vielleicht kurz lüften? Frische Luft, frische Gedanken.',
        'Wann hast du zuletzt etwas getrunken? Genau. Jetzt wäre ein guter Moment. 🫖',
        'Du darfst stolz sein und müde sein. Gleichzeitig.',
        'Kurz aufstehen, ein paar Schritte gehen, zurückkommen. Die Bücher sind noch da, versprochen.',
        'Musik an, wenn du magst. Widmen mit Soundtrack ist ausdrücklich erlaubt. 🎶',
        'Du bist ein Mensch, keine Druckmaschine. Pausen inklusive.',
        'Ein Stück Schoggi zwischendurch ist medizinisch gesehen völlig in Ordnung.',
        'Wenn die Hand müde wird: Finger einmal kräftig spreizen und wieder loslassen.',
        'Es ist okay, wenn nicht jede Widmung perfekt ist. Sie ist trotzdem wunderbar.',
        'Ein kurzes Katzenvideo zwischendurch? Wir sagen niemandem etwas. 🐈',
        'Atme ein, was du geschafft hast. Atme aus, was noch kommt.',
        'Sei sanft mit dir. Du machst hier gerade etwas sehr Liebevolles.',

        // Community, queerness, feminism
        'Queere Freude ist politisch. Und sie passt sehr gut zwischen zwei Buchdeckel. 🏳️‍🌈',
        'Irgendwo wird eine Jugendliche dieses Buch lesen und denken: Wow, so darf ich auch sein.',
        'Du schreibst für die, die sich zu oft zu klein gemacht haben. Damit sie wachsen.',
        'Feminismus ist Teamarbeit. Und du lieferst gerade Material fürs ganze Team.',
        'Wahlfamilie ist auch Familie. Und manche Leser*innen finden ihre gerade in einem Buch.',
        'Für jedes Coming-out, das ein bisschen leichter wird: danke.',
        'Gemeinsam sind wir laut, auch wenn jede für sich gerade leise liest.',
        'Liebe ist Liebe. Und Widmungen sind auch Liebe. Also ist das hier eine sehr liebevolle Veranstaltung.',
        'Niemand ist frei, solange nicht alle frei sind. Und jedes Buch hilft ein bisschen dabei.',
        'Du gibst Menschen Vorbilder, die sie selbst nie hatten.',
        'Wer sich in einem Buch wiederfindet, findet manchmal auch den Mut, sich im Leben zu zeigen.',
        'Für alle, denen gesagt wurde, sie seien «zu viel»: Hier ist ein Buch, das sagt: Du bist genug.',
        'Zärtlichkeit ist auch eine Form von Widerstand. Und dieser Stapel ist sehr zärtlich.',
        'Die Zukunft ist feministisch, queer und solidarisch. Und sie liest gerade dein Buch.',
        'Junge Menschen, die heute lesen, gestalten morgen die Welt. Gute Aussichten.',
        'Eine Gemeinschaft entsteht auch aus vielen einzelnen Bücherregalen.',
        'Mut ist ansteckend. Bücher sind ein sehr effizienter Übertragungsweg.',
        'Viele hier haben lange gewartet, jemanden wie dich zu lesen. Jetzt bekommen sie sogar eine Widmung dazu.',

        // Cute & light
        'Psst: Dieses Buch hat dir gerade zugezwinkert. 😉',
        'Die Post-its haben abgestimmt: Du bist ihre Lieblingsperson.',
        'Fun Fact: Handgeschriebene Widmungen machen Bücher 100 % kuschliger.',
        'Ein Buch, ein Name, ein Lächeln. Repeat.',
        'Dein Kugelschreiber verdient eigentlich auch einen Orden. 🖊️',
        'Irgendwo freut sich ein Briefkasten schon auf seinen Einsatz.',
        'Kleiner Applaus aus dem Off für diese Runde. 👏',
        'Wenn Bücher klatschen könnten, würden sie es jetzt tun.',
        'Die Bücher stehen Schlange, um von dir gewidmet zu werden. Sehr geordnet, dank Post-its.',
        'Du bringst gerade lauter kleine Glücksmomente auf den Weg. Ziemlich guter Job, oder?',
        'Die Bücher flüstern schon untereinander, wer als Nächstes dran ist.',
        'Dieser Kugelschreiber hat heute mehr Herz gesehen als mancher Liebesroman.',
        'Achtung: Beim Öffnen dieses Pakets besteht akute Gefahr von Glücksgefühlen. 📦',
        'Die Post-its sind übrigens sehr stolz auf ihre Nummern.',
        'Wenn Widmungen Konfetti wären, wäre der Boden jetzt sehr bunt. 🎉',
        'Zwischenbericht der Bücher: Alle sind sehr aufgeregt.',
        'Irgendwo räumt gerade jemand schon einen Platz im Büchergestell frei.',
        'Die Couverts stehen bereit und üben ihr schönstes Rascheln.',
        'Ein Buch mit Widmung ist wie ein Buch, nur mit Glitzer.',
        'Offizielle Mitteilung: Du bist gerade ein Level aufgestiegen. 🏆',
        'Gewidmete Bücher stehen im Regal ein kleines bisschen aufrechter.',
        'Die Tinte in deinem Stift macht heute Überstunden. Und zwar sehr gern.',
        'Schnell, ein Buch hat gerade «Ich auch, ich auch!» gerufen.',
        'Wissenschaftlich nicht bewiesen, aber: Gewidmete Bücher riechen besser. 📖',
        'High five! ✋ (Bitte mit der Hand, die nicht schreibt.)',
    ];

    /**
     * Books until the next message.
     */
    public static function gap(): int
    {
        return random_int(self::MIN_GAP, self::MAX_GAP);
    }

    /**
     * A message not shown yet in this sitting (starting over once all were shown).
     *
     * @param  array<int, int>  $seen  Indexes already shown
     * @return int Index into MESSAGES
     */
    public static function pick(array $seen): int
    {
        $left = array_values(array_diff(array_keys(self::MESSAGES), $seen));

        return $left ? $left[array_rand($left)] : array_rand(self::MESSAGES);
    }
}
