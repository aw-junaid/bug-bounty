# Preventing SQL Injection with Defensive Solutions

Up until now, we have focused on the offensive aspect of SQL injection. We saw how a malicious user can perform main attack techniques in previous chapters, and what consequences a successful SQL injection attack could have. In a general sense, we saw how, in principle, a SQL injection can quite easily result in a fully compromised database, which could leak sensitive information, give attackers full access to connected applications, or totally break the functionality of databases, applications, web services, or even connected devices, independent of the technology used.

We will focus more on the defensive side of things; now that we know that such an impressive and destructive vulnerability exists—and how simple, in principle, it would be to exploit it—how can we stop it? This is the question we are trying to answer here. Obviously, the solution to this problem is not simple, and it usually involves applying various defenses at the same time. We will go through the most important defenses, tackling what, generally speaking, the differences are in securing applications that deal with databases.

This chapter is split into the following sub-sections:
- Understanding general weaknesses and SQL injection enablers
- Treating user input
- Sanitization and input control
- Defending against SQL injection – code-level defenses
- Defending against SQL injection – platform-level defenses

## Technical Requirements

For this chapter, we strongly recommend familiarizing yourself with the main technologies involved in SQL injection scenarios. Besides going through the Technical requirements sections of previous chapters, we recommend taking a look at the documentation for some of the programming languages commonly used in conjunction with SQL so that we're on the same page when talking about some solutions that can be adopted in application development:
- https://docs.oracle.com/en/java/
- https://www.php.net/manual/en/
- https://docs.microsoft.com/en-us/dotnet/

## Understanding General Weaknesses and SQL Injection Enablers

SQL is an immensely powerful and effective tool for interacting with relational databases as it provides an opportunity to perform various tasks through the wide array of functions and commands available. Unfortunately, from a security standpoint, this boon is also a bane; allowing access to many different types of operations means that if no control is in place, anyone could potentially turn an application that utilizes databases on its head, leaving malicious imagination as the only limit to what attackers could achieve.

You saw firsthand what a vulnerable application can lead to (and we hope you also had fun in the process) in the previous chapter, and if you've reached this point in the book, you may also be wondering whether there's any way to improve security to prevent all of this. SQL databases are still extensively used today, so you can probably guess that the short answer is definitely yes. The long answer is that these defenses need to be applied all at once.

When exploring the challenges of Magical Code Injection Rainbow or even the Mutillidae II web application, we saw how simple solutions alone may not be enough as there is usually a way to bypass them. However, if defense was applied at multiple points in the application, these workarounds would not work anymore due to the presence of other simultaneous defenses that may render SQL injection attacks almost impossible without the existence of other vulnerabilities.

The main problem behind a SQL injection is how user input can interact with the actual syntax of SQL, mainly because, at the code level, SQL statements are usually constructed from text strings. Various programming languages use specific functions that take text strings as an argument. This text string is obviously written in SQL syntax in order to be interpreted as SQL input. The following is a SQL String declaration from the vulnerable Java web service we used in Chapter 4, Attacking Web, Mobile, and IoT Applications:

```java
String query = "SELECT * FROM " + USER_TABLE + " WHERE username='" + user_id + "' AND password='" + password + "'";
```

The query string will then be sent as a command to the database using `executeQuery(query)`, a Java function that, within a database connection, sends the input string to the database so that it can be processed.

You can see that while some parts of the query have fixed content, as delimited by double quotes, other parts are made up of previously declared variables. You can already tell where we are going now as you have already seen a SQL injection in action. By inserting a malicious payload into the query structure, attackers could in fact make it possible to execute arbitrary commands as if they were writing parts of the query themselves. In the attacks we previously mentioned, it's enough for an attacker to insert the malicious payload in place of the `user_id` parameter, altering the structure of the query in doing so. The resulting query, with respect to a tautology attack for bypassing authentication, would be the following:

```sql
SELECT * FROM USER WHERE username='' OR 1=1 -- -' AND password='password'
```

Inserting legal SQL expressions can alter the originally intended query functionality, as in this case, by using string delimiters and commenting. That is why user input needs to be taken into account in terms of security.

Approaching this aspect in a naive way can lead to a plethora of attack scenarios made possible by a SQL injection, possibly causing widespread damage to the integrity and security of an application. For this reason, when dealing with parts of code that take input from the outside of the application, this data needs to be treated safely and you should always consider the worst-case scenario—that this input is from a potentially malicious user or it has been purposely altered in order to cause damage to the application context. That's why we need to talk about trust in terms of user input.

## Treating User Input

What do we mean by trust when talking about security? It is actually one of the most important concepts when dealing with security in general, not just application security. Let's say you are walking along the street when a stranger approaches you asking for directions. You make a decision on whether to give directions to this person—sure, they could be an ill-intentioned person who is willing to attack you to steal your money, but you may decide that this risk is low; after all, there are many people around you, and you feel pretty confident that you'll be fine even if the situation takes a wrong turn. You then decide to trust this person in this specific case.

Of course, how wise this choice is depends on the context. Let's say you are now guarding an important energy plant when suddenly a person approaches you saying they forgot some important documents on the site and want to go through. As your role is making sure no one accesses the site without authorization, you have the specific duty to check the identity of this person and your default approach should be not letting anyone pass unless you recognize this person as having the right to do so based on their permissions.

When dealing with security, of course, the second approach is the one we should replicate. Zero-trust is the name of the game. Considering how malicious users can also spoof their identity, you should trust nobody by default. The basic assumption is considering the worst-case scenario every time; each user might be a malicious user because you cannot tell their intentions and cannot state otherwise. It would be a shame if you acted the other way around, trusting everybody, as one single malicious agent is enough to turn your application—and possibly your entire IT infrastructure—to smithereens.

In our case, this means applying defenses to our application and database because, as far as we know, anybody could perform SQL injection attacks against us—especially considering how simple it is to do so. These defenses can usually be summarized by the simple concept of sanitization and input control. The general solution of avoiding malicious input can in fact alter the behavior of our application, so that such input won't be interpreted by the application as possible un-envisioned instructions.

We will now explore what it means to sanitize input and apply defensive controls in this way, as well as consider all the stages that can be applied for foiling any possible SQL injection plan that an attacker may have.

## Sanitization and Input Control

We saw that all SQL (and other) databases are inherently vulnerable to SQL injection on their own as the only thing a database does is accept instructions. Therefore, we need to act at the early stages of the data flow, before a query actually reaches our database to prevent an injection from happening.

This is where sanitization comes in. Input, coming from the outside, is cleaned up from any possible malicious element that could result in dangerous commands. You can imagine this process like introducing a compulsory shower for people before they enter a public pool—you can assume that people have a good hygiene level, but since there is no guarantee of it, it's a wise choice to make up for people who don't by leveling out the field and making everyone do it. In most cases, this might not be necessary, but it ensures that cases in which a shower may be needed are covered.

Obviously, there is no single way in which sanitization can be done as these controls can be applied at various stages in the flow of the application. However, in most cases, there are two major areas for applying these defenses:
- **Application coding:** This is where the magic happens in terms of application functionality. Most defense mechanisms are in this domain, which we can call code-level defenses. By acting on code and information processing, most application attacks can be thwarted here, rigorously ensuring that input and commands are structured and formatted just the way we want. This can be done by transforming the input, accepting only some characters or input lengths, or generating queries dynamically. This generally foils SQL injection attempts, if done correctly.
- **Platform and infrastructure configuration:** Besides acting on the application code, security controls can be applied in the context in which the application is situated (in terms of the server and infrastructure in general). This includes the use of external modules, appliances, and network flow controls. While this might seem like overkill for secure application code, it can help drastically reduce successful attacks in general by preventing any malicious input from reaching the application altogether, thereby also avoiding collateral damage and other types of attacks against your application or systems. We will refer to these mechanisms as platform-level defenses.

All of these measures are a form of input control as they represent a way in which application input is checked, analyzed, and altered to be rendered inoffensive or blocked altogether before it reaches our running software.

Of course, applying just a single control mechanism at once does not guarantee our application is secure and safe against possible attacks. We already saw, in Chapter 4, Attacking Web, Mobile, and IoT Applications, that applying just a single means of control might not be enough. When we looked at Mutillidae II, we saw some simple client-side controls in action. The following screenshot will remind you of when we tried performing a regular SQL injection using the web form with client-side controls:


This client-side control just prevented information from being submitted with empty fields. Another measure that Mutillidae II has (client-side wise) is checking for forbidden characters—in this case, SQL injection enablers (such as single quotes and hyphens—this is called blacklisting, and we will see it in action shortly). Performing an SQL injection attempt using the input web form will fail and the application will return a message with a JavaScript alert:

While this is definitely a code-level defense, we already saw that client-side controls alone are useless as long as the server side is vulnerable. We inserted the malicious payload at the HTTP request level, totally bypassing the web form input and ignoring this defense. What does this mean? In the end, the best thing to do is to apply many different layers of defense, dramatically decreasing the likelihood of a successful attack.

In the following sections, we will see how these defense mechanisms can be applied, both at a code level and at a platform/infrastructure level, giving you a look at the tools available for securing an application against SQL injection.

## Defending Against SQL Injection – Code-Level Defenses

As we said earlier, applying code-level defenses, if done correctly, should foil all the plans of a malicious agent that wishes to attack your application. Of course, mistakes can always be made, and that is why the wisest thing to do is to apply various defense mechanisms all at once. In this section, we will explore the main tools at our disposal to thwart possible attacks against our application in terms of SQL injection. We will also see how these controls can be implemented into actual code in three common programming languages for developing web applications: Java, PHP, and .NET.

### Input Validation

Input validation is the process of accepting or rejecting input based on its content. We only want safe input to be processed by our application, preventing most of the attacks against us. So, only valid input, according to our rules, is accepted and processed by our application.

Validation follows two main approaches, which are also common to other areas of information security:
- **Blacklisting:** The approach of blacklisting consists of determining what is not allowed and refusing any input that falls into specific blacklisting rules. It's definitely easy to implement, but it might be defeated in cases where particular encodings that were not considered by the rules are used, or if new ways of attacking are discovered.
- **Whitelisting:** The approach of whitelisting is the logical opposite of blacklisting; these rules define what is allowed and everything that does not fall into this model is rejected, only accepting input that satisfies the correctness rules. Its implementation may be more difficult, but it definitely pays off as it can thwart newly discovered attacks, totally ignoring what is not originally envisioned by the application itself. After all, what would be the reason for a user to try some exotic input if not for performing some kind of attack attempt?

Input validation is probably the most basic and simple way to prevent malicious input from reaching our application and is by far the most common method.

#### Input Validation in Java

Java is a very flexible language and it has a long history of frameworks developed for it, many of these being particularly useful for developing web applications. Due to the plethora of available frameworks, we will focus on the most basic side of it.

As we said earlier, implementing blacklisting is quite easy, and you could simply check whether strings contain sensitive characters. In the following example, we are just blacklisting the single quote and the hyphen characters for simplicity.

The following code checks the input string, sent as a variable named `input`, and proceeds to build the SQL statement by calling another function named `constructQuery()`, but only if the string does not contain any blacklisted characters:

```java
String s = input;
if(s.contains("\'") OR s.contains("-")){
    throw new IllegalArgumentException("Illegal input");
} else {
    constructQuery(s);
}
```

Whitelists can be slightly more complex in terms of coding as we need to know exactly what a legal input is. If we are expecting a name to be inputted, we can use expressions based on the alphabet:

```java
String s = input;
if(s.matches("[[A-Z][a-zA-Z]*]")){
    constructQuery(s);
} else {
    throw new IllegalArgumentException("Illegal input");
}
```

You will notice that the inner logic is a reversed version of that for blacklisting; we only accept our input if we can tell for sure that it's legal.

Of course, there are more refined ways to apply input validation to Java code, and they tend to be dependent on the specific frameworks used. You can check the documentation for your framework, but the principle is always the same and you will find yourself understanding how it works easily.

#### Input Validation in PHP

As for PHP, like Java, implementations will depend on the framework used. Similarly to Java, it provides some useful functions that can help with implementing input validation in a simple way. One of these functions is `preg_match(regex, string)`, which, like the `String.matches()` function, checks whether a string matches the regular expression pattern. This can, of course, be used both for blacklisting and whitelisting:

```php
$s = $_POST['input'];
if(preg_match("/\'/", $s) OR preg_match("\-", $s)){
    // failed validation handling
}
```

Now, for the whitelisting case, we are keeping the same structure but reversing it in a logical way, as it now checks for any mismatches with the regular expression:

```php
$s = $_POST['input'];
if(!preg_match("/[[A-Z][a-zA-Z]*]/", $s)){
    // failed validation handling
}
```

In the end, different languages aside, the mechanisms are quite the same for both Java and PHP.

#### Input Validation in .NET

In a different way from Java and PHP, ASP.NET has various built-in controls that are used to develop an application. One simple control is `RegularExpressionValidator`, which follows the same approach as the pattern-matching functions we saw for Java and PHP. This control enforces both server- and client-side validation.

In the following example, we are applying the same whitelisting approach we saw for the previous two code examples, matching against a regular expression that only allows a string of letters, the first of which is uppercase:

```aspx
<asp:textbox id="input" runat="server"/>
<asp:RegularExpressionValidator id="inputRegEx" runat="server"
    ControlToValidate="input"
    ErrorMessage="Parameter must contain letters only, the first of which must be uppercase."
    ValidationExpression="[A-Z][a-zA-Z]*" />
```

ASP.NET also has other built-in controls, but this one is generally the most useful as it natively validates input against regular expressions.

### Parameterized Queries

Another way of defeating SQL injection is through the use of so-called parameterized queries. The main reasoning behind this is that input is never sent to the database as it is—that is, as a string, as is the case in dynamic string building—but it is instead serialized and stored in separate parameters (hence the name).

This is done by using variables when building the SQL statement, using identifiers as placeholders so that the actual string can be built safely. This is made even more accessible through the use of an API that is available for most modern programming languages and is used for interacting with database systems.

It's worth noting, however, that the use of parameterized queries alone does not mean an application is not vulnerable to SQL injection; sometimes, parameters can also contain stored procedures, which, if vulnerable, can still lead to an SQL injection. This is just one more reason to combine defense mechanisms.

Another perk of parameterized queries is the simplicity in which it's possible to convert already-existing dynamic strings for SQL queries into parameterized queries. We will see how this is done in Java, PHP, and .NET by starting from a regular (vulnerable) SQL query build:

```java
User = request("username")
Pass = request("password")
Query = "SELECT * FROM users WHERE username='" + User + "' AND password='" + Pass + "'"
Check = Db.Execute(Query)
If (Check) {
    Login()
}
```

Let's now see how we can parameterize the query in the preceding snippet.

#### Parameterized Queries in Java

One of the most used frameworks within Java when dealing with any database is the Java DataBase Connectivity (JDBC) framework. It's available natively and supports database connectivity independently from the database technology used, providing useful functions for connecting to databases. One of these is the `PreparedStatement` class, which allows the use of the following code:

```java
Connection con = DriverManager.getConnection(connectionString);
String query = "SELECT * FROM users WHERE username=? AND password=?";
PreparedStatement ps = con.prepareStatement(query);
ps.setString(1, user);
ps.setString(2, pass);
rs = ps.executeQuery();
```

The basic SQL statement is altered by replacing the values with question marks, which are then referred to by the `PreparedStatement` instance, `ps`. The `setString()` method then inserts the values in place of the placeholder question marks in the order that they are found in the original query (1 for `user` and 2 for `pass`). In the end, the `executeQuery()` method is called based on the prepared statement.

#### Parameterized Queries in PHP

PHP also has several frameworks for implementing parameterized queries. Some of these are available in PHP.

We will change the original snippet using the PHP Data Object (PDO) framework as it is the direct equivalent to the JDBC framework in Java in terms of compatibility and functionality. It's included in PHP version 5.1 onward:

```php
$query = "SELECT * FROM users WHERE user=? AND pass=?";
$stmt = $dbh->prepare($query);
$stmt->bindParam(1, $user, PDO::PARAM_STR);
$stmt->bindParam(2, $pass, PDO::PARAM_STR);
$stmt->execute();
```

This code is almost the perfect equivalent to the JDBC example; the statement is prepared starting from an SQL query, `$query`, containing placeholders in the form of a question mark. Then, the `bindParam()` function binds the input parameter to the question mark instances, ordered by number (1 for `$user` and 2 for `$pass`), specifying the data type of the parameter (`PDO::PARAM_STR` defines a string parameter). The prepared statement is finally executed with `execute()`.

#### Parameterized Queries in .NET

As for .NET, a way to implement parameterized queries is provided by the ADO.NET framework. The name derives from the previous ActiveX Data Object (ADO) technology on which it is based.

ADO.NET interacts with databases through the use of data providers, one for each supported database system. The code syntax varies with each provider, so we will show examples for the `System.Data.SqlClient` provider, which works with Microsoft SQL Server, and `System.Data.OracleClient` for Oracle Database. Let's first see what the code looks like with `SqlClient`:

```csharp
SqlConnection con = new SqlConnection(ConnectionString);
string Query = "SELECT * FROM users WHERE username=@user AND password=@pass";
cmd = new SqlCommand(Query, con);
cmd.Parameters.Add("@user", SqlDbType.NVarChar);
cmd.Parameters.Add("@pass", SqlDbType.NVarChar);
cmd.Parameters.Value["@user"] = user;
cmd.Parameters.Value["@pass"] = pass;
reader = cmd.ExecuteReader();
```

The parameters here are referred to with the `@` character, and they are added to the `cmd` prepared statement using `Parameters.Add()`, which sends in the parameter name and type (in this case, a string of characters denoted by `SqlDbType.NVarChar`).

`OracleClient` works in a similar way:

```csharp
OracleConnection con = new OracleConnection(ConnectionString);
string Query = "SELECT * FROM users WHERE username=:user AND password=:pass";
cmd = new OracleCommand(Query, con);
cmd.Parameters.Add("user", OracleType.VarChar);
cmd.Parameters.Add("pass", OracleType.VarChar);
cmd.Parameters.Value["user"] = user;
cmd.Parameters.Value["pass"] = pass;
reader = cmd.ExecuteReader();
```

This structure is almost identical to the `SqlClient` example. The only differences reside in the way that the parameters are referred to (with a semicolon in the query statement and with no special character elsewhere) and the object names.

### Character Encoding and Escaping

Another popular way of applying countermeasures against malicious input leading to SQL injection is by using specific character encoding and escaping techniques so that enabling characters are not sent to the database. This prevents the most common types of SQL injection attacks. There are also times where other defenses cannot be applied—for example, in databases that expect surnames, as some surnames may contain an apostrophe, such as O'Malley or O'Brian, which of course is still encoded as a single quote. In this case, there is no other way of allowing these surnames in your database.

This time, however, we are not acting at the same level of sanitization at the input level as instead, we are more concerned with sanitizing the output so that SQL statements are deprived of dangerous characters. The objective here is avoiding non-sanitized statements from traveling within the application flow and outside it when they are supposed to reach the database system.

Of course, these techniques vary from one database system to another due to the differences in syntax among them. We will see these techniques applied to MySQL, Microsoft SQL Server, and Oracle Database.

#### Character Encoding and Escaping in MySQL

As MySQL uses single quotes as a string termination, this character needs to be encoded when included in strings used for SQL statement construction. This can be done by replacing the single quote with two single quotes or by escaping the use of a single quote by using the backslash character (`\`) instead.

In Java, this can be done with a simple `replace()` function to replace occurrences of one character with other characters:

```java
query1 = query1.replace("'", "\'");
query2 = query2.replace("'", "''");
```

From the PHP side of things, within the `mysqli` framework, a PHP framework for interacting specifically with MySQL is available on PHP 5.x onward. There is a pretty nifty function named `mysql_real_escape_string()` that automatically puts a backslash in front of single quotes in a text string. This form of escaping is also applied to other dangerous characters:

```php
mysql_real_escape_string($parameter);
```

Of course, a `REPLACE` function is still available in PHP:

```php
SET @query1 = REPLACE(@query1, '\'', '\\\'');
SET @query2 = REPLACE(@query2, '\'', '"');
```

Another thing to keep in mind for sanitization is that other harmful characters include wildcards in a `LIKE` clause that can define any character, possibly causing tautology when added maliciously to a query. The most relevant wildcard is the `%` character, which corresponds to a wildcard of zero or more of any character. It can be escaped via a `replace()` function by adding a backslash before it:

```java
query3 = query3.replace("%", "\%"); // Java
```
```php
SET @query3 = REPLACE(@query3, '%', '\\%'); // PHP
```

You can apply this mechanism to any possibly dangerous character before sending a query statement to the database or other parts of the application.

#### Character Encoding and Escaping in SQL Server

The same assumptions and mechanisms we considered for MySQL can be applied to SQL Server. So, both the Java and PHP functions we have considered are valid for suppressing the single quote character. This time, when we talk about SQL server, we will consider the corresponding C# code, too, for replacing single quotes with double quotes:

```csharp
query3 = query3.replace("'", "''");
```

In addition to what we already considered for MySQL, SQL Server has an `ESCAPE` clause, which can be used to escape any character within a SQL query in a `LIKE` clause:

```sql
SELECT * from users WHERE name LIKE 'a\%' ESCAPE '\'
```

The preceding query escapes the backslash character in the `LIKE` clause, only returning records with a `a%` username (provided it exists).

#### Character Encoding and Escaping in Oracle Database

While considering the same assumptions made for MySQL, Oracle Database also usually relies on the PL/SQL language. This also has a `replace()` function, which can be used as follows:

```sql
query = replace(query, '''', '''''');
```

Oracle Database also supports an `ESCAPE` clause for the `LIKE` clauses, as was the case for SQL Server.

After dealing with these specific techniques, let's now move on to something more high-level in terms of code-level countermeasures.

### Secure Coding Practices

In most cases, the root of all the application security problems resides in the design and development phase. More often than not, in fact, designers and developers tend not to consider security aspects in the applications they find themselves working on, usually giving more importance to the functional aspects. This leads not only to vulnerabilities such as SQL injection but also to the increasing difficulty in remediating these vulnerabilities. Addressing security problems during the design and development phase takes much less effort as the only thing to do is apply the tools we described earlier and some secure design principles.

> **The OWASP SAMM Framework**
> OWASP, among other relevant projects, has devised an important framework to give organizations the tools to ensure the application of a model that promotes secure software development for all stages and stakeholders involved in the process of software design and development—the Software Assurance Maturity Model (SAMM). This framework provides a way for enterprises to self-assess their software development life cycle in terms of security, independent from the technologies used. For further information, you can access the project's web page at https://owasp.org/www-project-samm/.

We will now consider some good practices that can help in producing more secure code against SQL injection. This serves as an introduction to a security-focused approach for dealing with SQL injection from a coding perspective, introducing secure coding aspects to your application that can prove particularly effective and saving time and effort in subsequent stages of the application life cycle.

#### Introducing Additional Abstraction Layers

When we talk about abstraction layers, we mean different logical components, each devised to interact with your application logic. General examples of application layers are the presentation layer, which incorporates the more graphical and interactive aspects of the application, and the data access layer, designed to interact with data separately from the core application logic. Separating layers in an application generally improves security as moving from one layer to another is generally subject to more controls and makes applying security measures much more linear and practical.

An example of special additional layers introduced for security reasons is the ADO.NET framework we saw earlier, which can be used to interact with the database by introducing an additional level to the application specifically to send secure commands to the database, much like a dedicated data-access layer.

#### Managing Sensitive Data Securely

Another important security principle when designing a secure application is deciding how to manage and handle potentially sensitive data. Some of the information used and stored by the application might be extremely valuable to potential malicious attackers, including authentication information, such as passwords or credit card numbers, or even other sensitive or personal identifying information, such as names, surnames, physical addresses, and social security numbers.

When dealing with passwords, usually we are talking about particularly relevant information that is often specifically targeted by attackers. One of the most useful measures to take is storing passwords with a particularly Secure Hashing Algorithm (SHA), such as SHA-2, which provides one of the highest standards in cryptographic hash, producing one-way digests with 256- or 512-bit lengths. We saw, in our previous chapter, how other surpassed standards, such as Message Digest 5 (MD5), are no longer secure and could easily be broken into, with the attacker extracting the original password value, in the case of a successful SQL injection attack.

Another means of securing sensitive information includes masking data by only showing parts of the data to the application while keeping the original data unaltered. This can be achieved by treating this data appropriately in the application, applying pseudonymization techniques that substitute, for example, a large part of the information with special characters (such as `*`), making it recognizable to the owner of the information while at the same time not leaking more information than necessary to outsiders.

Of course, we want our application to be as invulnerable as possible to SQL injection, but considering that this risk will never be zero, this type of protection can definitely make a difference in securing an application and its data, minimizing the effects of a successful attack.

#### Stored Procedures

This measure is probably the most specific so far as it is directly linked to SQL databases. Stored procedures are specific instructions that are stored within the database itself and on which it's possible to apply stricter access control.

We saw how an application can potentially access the whole database so that when it's compromised with a SQL injection attack, it can give access to attackers, even to information residing on the same database in which the application database is not linked to the application itself, as we saw in the previous chapter with the shared MySQL database of the OWASP Broken Web Applications virtual machine, which allowed access to information belonging to other applications.

When you are using stored procedures, you can change the access permissions for the instructions contained within it, giving less privilege than the application to the commands that are executed. This means enforcing the principle of least privilege, which means that if for some reason an attacker can compromise the stored procedure containing the SQL commands, the damage would be contained due to the stricter access controls implemented.

This concludes our look at what can be done in terms of application development and coding. Applying as many of these measures as possible can definitely help in securing your application against SQL injection attacks. However, this is not the only way in which you can apply additional security measures to your application, as you can also act outside of it in the context that the running application is in. Let's look at what this means.

## Defending Against SQL Injection – Platform-Level Defenses

As mentioned earlier, platform-level defenses refer to all of the security measures we can apply at a platform and infrastructure level, possibly preventing malicious commands from entering or leaving the application and identifying and stopping harmful traffic. This also includes applying security measures to the database system itself.

Here, we are presenting a view of what can be done to secure an application against SQL injection by applying security controls and measures outside of it, in this way, granting additional layers of protection. This concept is called defense-in-depth and is one of the most relevant aspects of information security, helping to minimize possible threats against systems and applications alike.

### Application-Level Firewall Logic

The first, and probably most well-known, concept of protection is firewalling. A firewall is, generally speaking, an object that decides, usually according to some specific rules, whether a data flow—usually network traffic—is allowed to pass. In enterprise security, firewalls are usually physical appliances located at the boundaries of networks and sub-networks, acting as gatekeepers. These appliances are usually hardened computers whose only purpose is to filter traffic that enters or exits a network.

While, of course, traditional firewalls can help thwart any type of attack, we are more focused on the application-level side of things. Usually, firewalls exist independently of the presence of applications within a network, so we consider them external entities with respect to our scope. The same logic of firewalls, however, has also been applied to application-level concepts, filtering requests directed at application components in the same way that a traditional firewall would do, discarding whatever, according to a set rules, is deemed harmful to the specified application components. Let's see some examples of this concept.

#### Web Application Firewalls

When talking about web application security, not mentioning Web Application Firewalls (WAFs) is almost impossible. A WAF, usually in the form of a software solution or built within a specific network appliance, is designed to protect web applications from possible attacks against them, including SQL injection attacks. The most practical solution is using software-based WAFs. These are usually built into a web application or web server and require little configuration effort as they do not alter the web infrastructure surrounding the applications. Appliance-based WAFs, on the other hand, can be useful in certain scenarios as their activity does not take up web server resources, thereby not impacting functionality and performance. However, as application developers usually tend not to meddle with the surrounding infrastructure, we will mostly refer to software-based WAFs, also because of how simply they can be implemented.

WAFs tend to work by using filters that define what is accepted and what is not. These act as the rules of the WAF and they are responsible for accepting or rejecting requests. Filters need to be properly configured in order to prevent most attacks against your application. There are many ways in which filters can be implemented:
- **Web server filters:** These are filters that are installed as extra modules on a web server and tend to work as an additional component, evaluating requests entering the web server. Implementations can vary depending on the web server technology. Some examples include ModSecurity (available at https://modsecurity.org/), which works for Apache, and UrlScan by Microsoft, which is made for IIS web servers (https://docs.microsoft.com/en-us/iis/extensions/working-with-urlscan/urlscan-3-reference).
- **Application filters:** These filters can be implemented as additional modules of your application in the same programming language. For application developers, this option is often considered as these filters tend to be independent of the web server technology and can be included as additional application plugins. A notable example by OWASP is OWASP Stinger, which, however, it is not supported by OWASP anymore.
- **Web service filters:** Another useful option is filtering web service messages. This can be done in a custom way—for example, by filtering input messages containing SQL injection attempts or even output messages containing information disclosures.

WAFs are a very versatile tool for protecting web applications as they can be used in various modes to further improve security.

#### Application Intrusion Detection Systems

Aside from regular network-based Intrusion Detection Systems (IDSes), which can be used to identify cyberattacks in general and provide alerting functionalities, WAFs can be used as an application-level IDS to apply this concept directly to the specific applications it protects.

The way this works is to use the WAF in passive mode so that it can inspect the application request and send alerts if suspicious requests are found. This way, network administrators can be warned if a security incident occurs, thereby acting in a timely fashion based on the alert trigger.

#### Database Firewalls

The last firewall we will consider is the database firewall. A database firewall is basically a proxy server positioned between the application and its database that inspects the queries that are sent to it.

The application sends the query in the same way as if it were directed directly to the database, but the query is sent to the database firewall instead. At this point, the database firewall can inspect the query to check whether there is anything wrong with it (for example, whether it contains statements that deviate from the normal application behavior, tautologies, or any specified illegal characters). The proxy might then decide, based on its evaluation rules, whether the query can reach the database so that the database does not even receive harmful queries.

Since a database is usually contacted by an application to execute a specific set of functions, as defined by the application requisites, modeling whitelisting rules is the best approach, making it possible only for accepted queries to pass through the proxy.

### Database Server Security Mechanisms

Now that we have seen how it is possible to secure our application perimeter by blocking malicious input and output data, the only missing element to be secured is the database server itself. We can apply some concepts of what we have seen so far also to secure the database itself.

#### Protecting the Database Data

Besides applying the measures we already seen—such as hashing passwords and masking sensitive data—the most obvious security step to take in securing the database itself is applying cryptography to the stored data. Cryptography ensures that if the database is read directly—for example, if the data residing on it is copied or dumped in some way—its content remains protected and cannot be read by malicious attackers.

Cryptography by itself does not offer 100% certainty that the data can never be read by unauthorized individuals or organizations, but it does guarantee that breaking this protection requires a sufficiently long time and a lot of computational power, thereby rendering these attacks impractical or almost impossible.

Cryptographic algorithms always evolve over time, and they can be rendered obsolete whenever a major technological breakthrough takes place and more computational power is available to individuals and organizations. This has been the case for older cryptographical standards, such as the Data Encryption Standard (DES), which has been used for a long time. However, as common use computers have increasingly become more powerful, its cryptography was deemed no longer secure. Other, more reliable standards, such as 3DES—a triple iteration of the DES algorithm—are now considered insecure for the same reason. While a few years ago, they provided enough security, some actors might possess enough computational power to break them, thus being able to access protected information.

The de facto standard for modern cryptography is now the Advanced Encryption Standard (AES)—specifically, AES-256 with a 256-bit key, which provides a high guarantee of security. It works as a symmetrical cryptography algorithm as the same secret key is used for both the encrypting and decrypting of data. As long as the key remains secret, encrypted information will stay protected. To put this security feature in perspective, breaking a 256-bit key by brute-forcing, trying all the possible combinations, can require up to 2 to the power of 256 attempts (the resulting number is a 78-digit number). Even if a single attempt took a nanosecond (one billionth of a second), the seconds needed to try all the possible combinations exceeds any 68-digit number. If a program was used to break the encryption, it would run for far longer than the estimated life of the sun, which is estimated to be 5 billion years.

The only challenge to securing data through cryptography is keeping the encryption key secret. This is far from simple; if the key is stored on the database server as it is, it could be read by attackers. One of the most feasible solutions is storing the key in a safe way in a different location—for example, on a secure location in the application server. In order to use it, an attacker would need to compromise both the application server and the database server.

Encrypting data on a database with the appropriate mechanisms should provide enough security in case raw data gets exfiltrated or the actual device on which the data is stored is outright stolen, preserving the confidentiality of the information.

#### Protecting the Database Server

After acting on the stored data, let's now see how a database server can be protected. A database server is, first and foremost, a system within a network. As such, it might be inherently vulnerable to cyberattacks. There are a lot of ways to prevent or minimize the effects of malicious actions, of which we will look at some examples:
- **Patching:** The database server is a server in your infrastructure. As such, you need to be sure that it has the least number of vulnerabilities so that attackers have the fewest possible amount of ways to compromise it. A fundamental security principle is ensuring that software running on the database server is always up to date and has the most recent security updates installed. Most updates are released to provide remediation to vulnerabilities, some of which present critical risks to the server and the surrounding infrastructure. It's of the utmost importance to have security updates installed on not only the database system but also the operating system and all the software installed. Vulnerabilities could, in fact, be exploited one after the other, and the presence of more vulnerabilities increases the risk of a system being compromised. Patching can be enforced not only manually but also through automatic patching agents, which are widely used in various enterprise networks.
- **Enforcing the least privilege principle:** We already talked about the least privilege principle when dealing with stored procedures. This time, we need to address it more broadly. On an operating system, programs can be run at various levels, from a low-privileged to an administrator level. One way to improve security is by ensuring that database programs are run at a low privilege level in terms of reading, writing, and executing. Applying the least privilege principle ensures that, if the database is compromised, the actions resulting from this compromise are mitigated in their impact—an attacker has lower chances of causing damage to the system itself and possibly has a limited chance for lateral movement (which means attacking other systems in the network).
- **Enforcing authentication and monitoring controls:** Finally, another way to prevent attackers from causing damage is by improving the security controls relating to authentication and monitoring. This includes ensuring that passwords are not weak by enforcing a strong password policy, disabling default accounts (which are often targeted by attackers as they already know their username and only need to guess the password), and enabling logging so that possible authentication attempts are tracked, as well as actions on the server itself.

This concludes our overview of the more practical measures that can be taken against SQL injection in terms of platform-level defenses. Besides these, it's worth noting some more general principles to be taken into account when securing applications.

### Other Security Measures

In addition to what we have seen so far, the infrastructure and platform-level defense can also include some other general principles that, if followed, can further improve the overall security of your environment. Generally speaking, applying all the measures we've seen so far would definitely put your application and systems at quite a satisfying security level, but for the sake of completeness, we will now list some other possible tweaks that can provide additional protection against SQL injection.

#### Reducing Information Disclosure

When performing offensive actions against an application or a system, a malicious agent will always try everything in their power to obtain as much information as possible about your environment so that they can attempt various attacks depending on the information they obtain. Limiting the information that they can access can effectively reduce their attack potential, thereby minimizing the risk that your application will be compromised.

Here, we will present some areas in which reducing leaked information can prove useful and might effectively limit attackers' potential:
- **Not showing error messages:** When we dealt with offensive SQL injection techniques, we tried to rely as much as possible on error messages. Default SQL error messages can leak information regarding versioning and query syntax, and can also give other clues to attackers for trying other attacks. Showing error messages can give an attacker more hints than you would probably wish for, so avoiding the display of error messages is a good idea. Sometimes, it is a wise choice not to show that an error has occurred at all, avoiding possible blind SQL injection attempts. You could either not show any errors at all in your application, or else provide a general, customized HTTP error page (for example, an HTTP 500 error page). This, of course, can make things more difficult for debugging purposes, but if the application is in a production environment, this should not be an issue.
- **Prevent Google (and other search engine) hacking:** Google hacking techniques are a way to return specific information from websites by inserting specific operators within the search string of Google. Inserting certain keywords could allow attackers to obtain relevant information about your application by accessing specific web pages containing a specific keyword. This can be prevented by editing a file in the root directory of your website, which instructs search engines not to crawl your website so that inner pages cannot be accessed by them. This file is called `robots.txt`, and its content to prevent this behavior looks as follows:
    ```
    User-agent: *
    Disallow: /
    ```
    This means that web crawlers are not allowed to index your website, thereby preventing specific web searches from displaying content that could be used against you, which could provide useful information to attackers.

Reducing the quantity of information that an application shows, limiting it to what is only strictly necessary, greatly improves security as it discourages potential attackers from trying to compromise your application.

#### Secure Server Deployment

Another critical step for security is server deployment, which needs to be made in the least risky approach possible.

In general, it's best to keep your application infrastructure elements separated. This includes the application/web server and the database server. If they are deployed on the same machine, by compromising one, an attacker could easily obtain access to the other one. This could, in addition, defeat the point of measures such as cryptography as an attacker would have access to the database and the stored encryption key at the same time. In general, splitting your architecture into more components helps preserve security by reducing the impact caused by possible malicious actions.

Deployments should also take place while guaranteeing that the machine's configuration is made secure by removing settings that are typical of the testing and debugging stages of development. For example, some exposed services used for debugging and remote access can provide attack points that attackers could use to compromise your systems and application.

#### Network Access Control

Finally, another security measure that is used in conjunction with separate machines for each server is applying Network Access Control (NAC). NAC involves only allowing selected hosts to connect to specific servers and services. In a setting in which we have deployed a web server and database server separately, for example, we would want the database server to only accept connections coming from that web server. Otherwise, an attacker who has gained access to the network could interact with the database server directly, bypassing most of the security measures we put in place at the application level.

This can be achieved, for example, by allowing a connection from hosts that have a specific certificate installed or through the use of firewalls, only allowing connections from specific hosts. Routers could also be instructed to enforce this principle by implementing access control lists that provide a set of hosts from which to accept connections.

## Summary

That was quite a lot of information. When dealing with defense mechanisms, there are a lot of factors to consider, and the more defense mechanisms you apply to your context, the less chance an attacker has to cause damage to your environment. For this reason, using all of the security measures we described in this chapter—or almost all, depending on the context and the applicability of these controls—is very important for security.

This chapter first covered the general aspects of countermeasures against SQL injection—specifically, dealing with user input and controlling data flows. Then, we analyzed specific defenses for dealing with application coding, general patterns to follow in application development, and securing the infrastructure surrounding the application.

As for code-level defenses, we saw how to validate input, using both blacklisting and whitelisting, to only accept safe input. Then, we applied sanitizing measures, both for query statement construction—using parameterized queries—and character encoding and escaping, to avoid harmful characters that could enable SQL injection. Secure coding practices were also examined, showing some rationales for building code that is secure against SQL injection and, collaterally, other attacks in general.

Platform-level defenses can fall outside the strict scope of application security and involve more general security principles. We saw how firewall logic can be applied to application components through WAFs and database firewalls. We then analyzed ways to secure the database itself, which is one of the most important parts of database-reliant application architecture, both considering the data and the database server. Finally, other general security measures were discussed in order to improve the overall security of your application.

While all of these measures help against SQL injection, you will have realized that the focus tends to be directed more toward security in general. The next chapter will put all you have learned in perspective, giving you a way to critically examine all that you have learned. Consider it as looking back after a long journey, putting you in the position to think about all that you have seen and experienced. Hopefully, you will have become more educated not only about SQL injection but also about security in general. Maybe (just maybe) this will also spark an interest in cybersecurity in a broader sense!
