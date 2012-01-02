Introduction
============

[osTicket](http://osticket.com) is a nice looking support tracking system with some value in places as the gui, the concept of the staff panel, an API and some other stuff I liked.  The problem I had with it is that the mail handling isn not all that intelligent.   So I tried to find the repo of the 1.6.0 ST version.   Turned out that they do not have this openened up for the public.  the fact that patches have to be submitted to the message board without decent version control makes it just hell to mod professionally.  

ThenI found out that [Jens Rantil](https://github.com/JensRantil/) had the same issue and he put the code up in github along with his mods for CC support.  I wanted to do this myself too but I actually liked most of his changes so I decided to take his version as the base of my fork.  Make note that initially I want to focus on the mail handling, so you will not notice a lot of changes unless you depend on the cron mail import job where most of my changes are located.

I also have issues with the short coding style being used.  I also have some issues with the way some of this is coded, I hope to improve it.  Please be aware that this isn't the official repo as that doesn't exist AFAIK.

#### Main features of this fork
- [Jens Rantil](https://github.com/JensRantil/) mods: customize who you are replying to, CC and BCC in replies, show TO and CC headers in GUI and more.
- Massively improved mail/ticket handling, based on using message_id, references, followed_up and in_reply_to headers from the mails.  As the last resort we will look into the subject.  Mail can be left on the server.  There are some massive issues in the current code due to using the sequence number of a mail in the mailbox (imap is my focus) as a key.  I honoustly could not figure out why I seem to be the only one with these problems.
- The most popular report mod from [Scott Rowley](http://sudobash.net/?p=821), [see forum thread](http://osticket.com/forums/showthread.php?t=6171) 
- Small fixes along the way, removing deprecated php calls (php5 is the target).
- Bugs I can fix that I read about in the forums will be applied, especially if I have the issue myself.

Hidden agenda
=============
- I hope that maybe my fixes will be taken over in the original version, but I cannot spend my time on this without a decent repo behind it. 
- I also hope to make that pull request to Jens once the code is in shape.
- Perhaps build a community of the disgruntled users and developers out there in the forums.  I really did not like some of the arguments from the osTicket people when people are asking for the repo or why there isn't any.  These are people that want to contribute and they are making it hard to do this well... WHY ?

Quick-start
===========

1. Just check this code out
2. Check the DB schema (an sql upgrade script will soon follow once all mods are final to support the advanced email handling).
3. Run it !

Added components
================

#### Static Tools class (class.tools.php)

This is a very simple class with small utilities I made to make my life easier, all static functions not belonging to a class will go there.

Dependencies
============
 - Most probably you will need some recent PHP5 version, the original code seems to be PHP4 style, specifically the classes lacking explicit __constructor functions.  I will probably not look at supporting PHP4.

Feedback
========

Don't hesitate to submit feedback, bugs and feature requests ! My contact address is glenn at byte-consult dot be or right here.
