# Ollama AI Chatbot Setup Guide

This guide will help you set up Ollama to power your pizzeria chatbot with AI responses based on your database.

## Prerequisites

- Windows 10/11 (64-bit)
- XAMPP with PHP and MySQL running
- Your pizzeria database set up and populated

## Step 1: Install Ollama

1. **Download Ollama for Windows:**
   - Visit [https://ollama.ai/download](https://ollama.ai/download)
   - Download the Windows installer
   - Run the installer and follow the installation prompts

2. **Verify Installation:**
   ```cmd
   ollama --version
   ```

## Step 2: Download and Set Up the AI Model

1. **Open Command Prompt or PowerShell as Administrator**

2. **Download the recommended model (Llama 3.2 3B):**
   ```cmd
   ollama pull llama3.2:3b
   ```

3. **Alternative models you can try:**
   ```cmd
   # Smaller, faster model (good for lower-spec machines)
   ollama pull phi3:mini

   # Larger, more capable model (requires more RAM)
   ollama pull llama3.1:8b
   ```

## Step 3: Start Ollama Service

1. **Start Ollama server:**
   ```cmd
   ollama serve
   ```

2. **The service will start on `http://localhost:11434`**

3. **Keep this terminal window open while using the chatbot**

## Step 4: Test the Setup

1. **Test Ollama directly:**
   ```cmd
   ollama run llama3.2:3b
   ```
   Type a test message and press Enter. Type `/bye` to exit.

2. **Test via API (optional):**
   Open another Command Prompt and run:
   ```cmd
   curl http://localhost:11434/api/generate -d "{\"model\": \"llama3.2:3b\", \"prompt\": \"Hello, how are you?\", \"stream\": false}"
   ```

## Step 5: Configure Your Chatbot

1. **Update the model in chatbot.php (if needed):**
   - Open `api/chatbot.php`
   - Find the `sendToOllama` function
   - Change the default model parameter if you want to use a different model:
   ```php
   function sendToOllama($prompt, $model = 'llama3.2:3b') {
       // Change 'llama3.2:3b' to your preferred model
   ```

2. **Ensure your database is populated:**
   - Make sure your `pizzas` table has data
   - The chatbot will pull pizza information from your database

## Step 6: Test Your Chatbot

1. **Start XAMPP services (Apache and MySQL)**
2. **Make sure Ollama is running** (`ollama serve`)
3. **Open your pizzeria website**
4. **Test the chatbot with questions like:**
   - "What pizzas do you have?"
   - "Tell me about Margherita pizza"
   - "What are your hours?"
   - "Show me vegetarian options"

## Troubleshooting

### Ollama Not Responding
- Ensure Ollama service is running: `ollama serve`
- Check if port 11434 is available
- Try restarting Ollama

### Model Too Slow
- Use a smaller model: `ollama pull phi3:mini`
- Update the model in `api/chatbot.php`

### Database Connection Issues
- Verify XAMPP MySQL is running
- Check database credentials in `config/database.php`
- Ensure your pizzas table has data

### Chatbot Fallback
- If Ollama is unavailable, the chatbot will use fallback responses
- Check the browser console for error messages
- Review PHP error logs in XAMPP

## Performance Tips

1. **System Requirements:**
   - Minimum: 8GB RAM
   - Recommended: 16GB RAM for better performance
   - SSD storage for faster model loading

2. **Model Selection:**
   - `phi3:mini` - Fast, lightweight (3.8GB)
   - `llama3.2:3b` - Balanced performance (2.0GB)
   - `llama3.1:8b` - High quality (4.7GB)

3. **Optimization:**
   - Keep Ollama service running to avoid startup delays
   - Use SSD storage for faster model access
   - Close unnecessary applications to free up RAM

## Advanced Configuration

### Custom Model Parameters
You can adjust the AI behavior by modifying parameters in `api/chatbot.php`:

```php
$data = [
    'model' => $model,
    'prompt' => $prompt,
    'stream' => false,
    'options' => [
        'temperature' => 0.7,    // Creativity (0.0-1.0)
        'max_tokens' => 500,     // Response length
        'top_p' => 0.9,         // Response diversity
        'repeat_penalty' => 1.1  // Avoid repetition
    ]
];
```

### Running Ollama as a Service
To automatically start Ollama with Windows:

1. Create a batch file `start_ollama.bat`:
   ```batch
   @echo off
   cd "C:\Users\%USERNAME%\AppData\Local\Programs\Ollama"
   ollama serve
   pause
   ```

2. Add to Windows startup folder:
   - Press `Win + R`, type `shell:startup`
   - Copy the batch file to this folder

## Support

If you encounter issues:

1. Check Ollama logs: `ollama logs`
2. Review PHP error logs in XAMPP
3. Verify database connectivity
4. Test API endpoint directly with curl or Postman

For more help, refer to:
- [Ollama Documentation](https://github.com/ollama/ollama/blob/main/README.md)
- [Ollama Models](https://ollama.ai/library)