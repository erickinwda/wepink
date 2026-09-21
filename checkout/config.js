/* config.js — Configuração do checkout WePink */

window.API = "https://wepink-sable.vercel.app/api";
window.LOGO = "../loja/images/logo2.png";

window.KITS = {
  "1": {
    id: "1",
    img: "../loja/images/kitbodysplash.png",
    title: "Kit Body Splash",
    old: 249.90,
    price: 34.90,
    units: 1,
    slug: "kit-1-body-splash"
  },
  "2": {
    id: "2",
    img: "../loja/images/kitbodysplash.png",
    title: "Kit 2 Body Splash",
    old: 299.80,
    price: 58.80,
    units: 2,
    slug: "kit-2-body-splash"
  }
};

window.SHIPPING = [
  { id: "free", label: "Correios (grátis)", eta: "Entrega em 5-10 dias", value: 0 },
  { id: "fast", label: "Transportadora (grátis)", eta: "Entrega em 3-5 dias", value: 0 }
];

window.ORDER_BUMPS = [
  {
    id: "bump-1",
    title: "Brinco Aniversário WePink",
    desc: "Brinco exclusivo com brilho e perola",
    old: 129.00,
    price: 12.90,
    img: "../loja/images/kit.jpg"
  }
];