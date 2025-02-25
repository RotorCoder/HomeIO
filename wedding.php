<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Wedding</title>
    <style>
        body {
            font-family: 'Cormorant Garamond', serif;
            margin: 0;
            padding: 0;
            background-color: #fafafa;
            color: #333;
        }
        
        header {
            background-color: #f8f9fa;
            text-align: center;
            padding: 100px 20px;
            background-image: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), url('/api/placeholder/1200/800');
            background-size: cover;
            background-position: center;
        }

        h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .date {
            font-size: 1.5em;
            margin-bottom: 40px;
        }

        .main-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        section {
            margin-bottom: 60px;
            text-align: center;
        }

        h2 {
            color: #666;
            margin-bottom: 20px;
        }

        .details {
            line-height: 1.6;
        }

        .countdown {
            font-size: 1.2em;
            margin: 40px 0;
        }

        footer {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            margin-top: 40px;
        }

        @media (max-width: 600px) {
            h1 {
                font-size: 2em;
            }
            .date {
                font-size: 1.2em;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>[Bride] & [Groom]</h1>
        <div class="date">[Wedding Date]</div>
        <div>[Location]</div>
    </header>

    <div class="main-content">
        <section>
            <h2>Our Story</h2>
            <p class="details">
                [Your love story goes here]
            </p>
        </section>

        <section>
            <h2>Wedding Details</h2>
            <p class="details">
                Time: [Time]<br>
                Venue: [Venue Name]<br>
                Address: [Venue Address]
            </p>
        </section>

        <section>
            <h2>Schedule</h2>
            <p class="details">
                [Time] - Ceremony Begins<br>
                [Time] - Cocktail Hour<br>
                [Time] - Reception<br>
                [Time] - Dancing
            </p>
        </section>
    </div>

    <footer>
        <p>We can't wait to celebrate with you!</p>
    </footer>
</body>
</html>