import React, { useState } from "react";

interface Company {
  id: string;
  name: string;
  description: string;
}

const mockCompany: Company = {
  id: "1",
  name: "Odensaker AB",
  description: "Beskrivning av företaget"
};

export default function EditCompanyPage() {
  const [company, setCompany] = useState<Company>(mockCompany);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setCompany({ ...company, [e.target.name]: e.target.value });
  };

  const handleSave = () => {
    // Spara logik här (API-anrop etc)
    alert("Företaget sparat!");
  };

  return (
    <div>
      <h1>Redigera företag</h1>
      <label>
        Namn:
        <input name="name" value={company.name} onChange={handleChange} />
      </label>
      <br />
      <label>
        Beskrivning:
        <textarea name="description" value={company.description} onChange={handleChange} />
      </label>
      <br />
      <button onClick={handleSave}>Spara</button>
    </div>
  );
}