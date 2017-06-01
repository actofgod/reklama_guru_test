
INSERT INTO `users` (`name`, `gender`, `email`) VALUES
 ('test1', 0, ''),
 ('test2', 1, 'test2@domain1.com'),
 ('test3', 1, 'test3@domain1.com,test3@domain2.com'),
 ('test4', 1, ' test4@domain1.com , test4@domain2.com , test41@domain1.com , test42@domain2.com'),
 ('test5', 1, ' test5@domain1.com '),
 ('test6', 1, ' invalid , invalid@ ,     @ ,  '),
 ('test6', 1, ' @invalid.com '),
 ('test7', 1, ' @invalid.com , test7@domain1.com'),
 ('test8', 1, ' test8@domain1.com ,@invalid.com'),
 ('test9', 1, ' test9@domain1.com ,@invalid.com, test9@domain2.com');
