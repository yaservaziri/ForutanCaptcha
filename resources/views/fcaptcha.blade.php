<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Human Verification</title>
  <style>
    body {
      background-color: #111827;
      color: #f3f4f6;
      font-family: system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
    }

    .container {
      width: 100%;
      max-width: 768px;
      padding: 1.5rem;
    }

    h1 {
      font-size: 2.5rem;
      font-weight: bold;
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .error-box {
      max-width: 600px;
      margin: 0 auto 1.5rem auto;
      background-color: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      border-radius: 1rem;
      padding: 1rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .error-box ul {
      margin: 0;
      padding-left: 1.2rem;
      font-size: 0.875rem;
    }

    .grid-container {
      display: grid;
      grid-template-columns: repeat(3, 96px); 
      gap: 1.5rem;
      justify-content: center;
      width: fit-content;
      margin: 0 auto 2rem auto;
    }
    
    @media (min-width: 640px) {
      .grid-container {
        grid-template-columns: repeat(3, 96px); 
      }
    }
    
    @media (min-width: 768px) {
      .grid-container {
        grid-template-columns: repeat(4, 96px);
      }
    }
    
    @media (min-width: 1024px) {
      .grid-container {
        grid-template-columns: repeat(6, 96px);
      }
    }

    .fade-in {
      opacity: 0;
      animation: fadeInUp 0.5s ease-out forwards;
    }

    @keyframes fadeInUp {
      0% { opacity: 0; transform: translateY(20px) scale(0.9); }
      100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    input[type="checkbox"] {
      display: none;
    }

    .image-label {
      display: block;
      width: 96px;
      height: 96px;
      background-color: white;
      border-radius: 9999px;
      overflow: hidden;
      border: 1px solid #d1d5db;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .image-label img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      pointer-events: none;
      border-radius: inherit;
    }

    input[type="checkbox"]:checked + label {
      border: 2px solid #34d399;
      box-shadow: 0 0 10px rgba(52, 211, 153, 0.8);
      transform: scale(1.1);
    }

    .reference {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .reference p {
      font-size: 1.125rem;
      color: #d1d5db;
      margin-bottom: 0.5rem;
    }

    .reference img {
      width: 96px;
      height: 96px;
      object-fit: cover;
      border-radius: 9999px;
      border: 2px solid #6366f1;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .submit-button {
      width: 100%;
      padding: 0.75rem 1.5rem;
      font-size: 1.125rem;
      font-weight: bold;
      background: linear-gradient(to bottom right, #34d399, #10b981, #06b6d4);
      color: #111827;
      border: 1px solid rgba(16, 185, 129, 0.4);
      border-radius: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(6, 182, 212, 0.25);
    }

    .submit-button:hover {
      background: linear-gradient(to bottom right, #6ee7b7, #22d3ee);
      transform: translateY(-4px) scale(1.02);
      box-shadow: 0 10px 30px rgba(34, 211, 238, 0.35);
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Human Verification</h1>

    @if ($errors->any())
      <div class="error-box">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('fcaptcha.verify') }}" method="POST">
      @csrf

      @php
      $context = session('fcaptcha_context');
      $token = session("fcaptcha_token.$context");
      @endphp

      <input type="hidden" name="fcaptcha_token" value="{{ $token }}">

      <div class="grid-container">
        @foreach ($images as $index => $image)
          @php $delay = 100 + ($index * 80); @endphp
          <div class="fade-in" style="animation-delay: {{ $delay }}ms;">
            <input type="checkbox" name="selected_images[]" value="{{ $image->hash }}" id="image_{{ $loop->index }}">
            <label for="image_{{ $loop->index }}" class="image-label">
              <img loading="lazy" src="{{ route('fcaptcha.image', $image->hash) }}" alt="Captcha Image">
            </label>
          </div>
        @endforeach
      </div>

      <div class="reference">
        <p>Select the images that are similar to this one:</p>
        <img src="{{ route('fcaptcha.image', $reference->hash) }}" alt="Reference Image">
      </div>

      <button type="submit" class="submit-button">
        ✅ Submit Selection
      </button>
    </form>
  </div>
</body>
</html>
