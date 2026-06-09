
  // Use require to avoid missing type declarations for 'react-dom/client'
  // @ts-ignore
  const { createRoot } = require("react-dom/client");
  import App from "./app/App.tsx";
  // @ts-ignore
  require("./styles/index.css");

  createRoot(document.getElementById("root")!).render(<App />);
  