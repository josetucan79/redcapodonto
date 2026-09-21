import { useCircleProgress } from "@/Components/CircleProgress/index.js";

const circle = useCircleProgress(".progress-ring");

const input = document.getElementById("progress-input");
const valueDisplay = document.getElementById("progress-value");
input.addEventListener("input", (e) => {
  const value = e.target.value;
  circle.setProgress(value);
  valueDisplay.textContent = `${value}%`;
});