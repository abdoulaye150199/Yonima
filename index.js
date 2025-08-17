const express = require('express');
const path = require('path');
const app = express();
const port = process.env.PORT || 3000;

// Servir les fichiers statiques
app.use(express.static('public'));

// Point d'entrée principal
app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, 'public/index.php'));
});

app.listen(port, () => {
    console.log(`Server running on port ${port}`);
});